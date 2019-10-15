<?php

namespace Genesos\Operation\Command;

use DateTime;
use Genesos\Operation\Util\SeleniumUtil;
use PHPWebDriver_NoSuchElementWebDriverError;
use PHPWebDriver_WebDriverBy;
use PHPWebDriver_WebDriverSession;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WebTool extends Command
{
    /**
     * @var PHPWebDriver_WebDriverSession
     */
    private $session;
    private $vars = [];
    /**
     * @var string[]
     */
    private $lines;
    /**
     * @var string
     */
    private $save_dir;

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:WebTool')
            ->setDescription('웹툴입니다. TXT 파일(혹은 폴더)를 넣어주세요.')
            ->addArgument('filename', InputArgument::REQUIRED | InputArgument::IS_ARRAY, '파일명');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(
            [
                '웹툴',
                '=====================================',
            ]
        );
        $txts = $this->loadTxts($input);
        $this->save_dir = sys_get_temp_dir() . '\\__webtool_' . uniqid('webtool', true);
        if (!mkdir($this->save_dir) && !is_dir($this->save_dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $this->save_dir));
        }

        $this->session = SeleniumUtil::boot('firefox', [
            'browser.download.dir' => $this->save_dir,
        ]);
        foreach ($txts as $txt) {
            $lines = file($txt);
            $this->lines = $lines;
            $this->commandLoop();
        }
        echo '프로그램 종료' . PHP_EOL;
        $this->session->close();
    }

    private function runLine($line_count)
    {
        $line = $this->lines[$line_count];
        $line = trim($line);

        $token_found = preg_match_all('/([^"\']\S*|"[^"]+"|\'[^\']+\')(\s+|$)/', trim($line), $matches);
        if (!$token_found) {
            return false;
        }
        $tokens = collect($matches[1])
            ->map(static function ($str) {
                $str = trim($str);
                $first_char = $str[0];
                if ($first_char === '"' || $first_char === '\'') {
                    $str = substr($str, 1, -1);
                }
                return $str;
            })
            ->all();
        $command = $tokens[0];
        $args = array_slice($tokens, 1);
        if ($command === 'open') {
            $url = $args[0];
            $this->session->open($url);
            echo "{$url} 페이지를 열고 있습니다." . PHP_EOL;
            sleep(1);
        } elseif ($command === 'wait') {
            $url = $args[0];
            $this->commandWait($url);
        } elseif ($command === 'set') {
            $dest = $args[0];
            $src = $args[1];
            $this->commandSet($dest, $src);
        } elseif ($command === 'loop_date') {
            $begin_var = date_create($this->castVar($args[0]));
            $target_var = $args[1];
            $end_var = date_create($this->castVar($args[2]));
            $term = $args[3];

            $iterator = clone $begin_var;
            echo "루프시작" . PHP_EOL;
            echo "    {$begin_var->format('Y-m-d')} 부터 {$end_var->format('Y-m-d')} 까지." . PHP_EOL;
            echo "    {$target_var} 에 {$term} 마다" . PHP_EOL;
            while ($iterator <= $end_var) {
                $this->commandSet($target_var, clone $iterator);
                $this->commandLoop($line_count + 1);

                $old_iterator = clone $iterator;
                $iterator->modify($term);
                if ($old_iterator == $iterator) {
                    echo '루프오류 (날짜가 바뀌지 않음)' . PHP_EOL;
                    break;
                }
            }
            echo "루프종료" . PHP_EOL;
            return true;
        } elseif ($command === 'add_date') {
            $dest_name = $args[0];
            $term = $args[1];
            $dest = $this->castVar($dest_name);
            $dest->modify($term);
            echo "`{$dest_name}`에 {$term} 만큼 날짜 변경 시도" . PHP_EOL;
            $this->commandSet($dest_name, $dest);
        } elseif ($command === 'html_type_date') {
            $selector = $args[0];
            $val = $this->castVar($args[1]);
            $format = $args[2];

            $val = $val->format($format);
            $this->commandHtmlType($selector, $val);
        } elseif ($command === 'html_type') {
            $selector = $args[0];
            $val = $this->castVar($args[1]);
            $this->commandHtmlType($selector, $val);
        } elseif ($command === 'html_click') {
            $selector = $args[0];
            $this->commandHtmlClick($selector);
        } elseif ($command === 'wait_download') {
            $file_name = $args[0];
            $this->commandWaitDownload($file_name, $this->save_dir);
        }
        return false;
    }

    /**
     * @param $target_url
     */
    protected function commandWait($target_url)
    {
        $target_url = preg_replace('/https?:\/\//', '', $target_url);
        echo '로그인을 진행해주세요.' . PHP_EOL;
        foreach (range(1, 100) as $second) {
            $current_url = $this->session->url();
            $strpos = strpos($current_url, $target_url);
            if ($strpos !== false && $strpos < 10) {
                echo "{$target_url} 페이지 열림 확인!" . PHP_EOL;
                break;
            }
            sleep(1);
            echo "    {$target_url} 페이지를 {$second}초째 대기 중" . PHP_EOL;
        }
    }

    protected function commandLoop($begin = 0): void
    {
        foreach (range($begin, count($this->lines) - 1) as $line_count) {
            $is_looped = $this->runLine($line_count);
            if ($is_looped) {
                break;
            }
        }
    }

    /**
     * @param InputInterface $input
     *
     * @throws \Exception
     */
    private function loadTxts(InputInterface $input)
    {
        $uris = $input->getArgument('filename');
        $target_txts = [];
        foreach ($uris as $uri) {
            if (is_dir($uri)) {
                $target_txts[] = glob($uri . '/*.txt');
            } else {
                $target_txts[] = $uri;
            }
        }
        $txts = collect($target_txts)->flatten()->sort(static function ($a, $b) {
            $a_num = (int)preg_replace('/(?<=\d)\D.+/Uu', '', basename($a));
            $b_num = (int)preg_replace('/(?<=\d)\D.+/Uu', '', basename($b));
            return $a_num > $b_num;
        })->all();
        return $txts;
    }

    private function commandSet($dest, $src)
    {
        $src_result = $this->castVar($src);
        $dest_name = substr($dest, 1);
        $this->vars[$dest_name] = $src_result;
        if ($src instanceof DateTime) {
            echo "`{$dest}`에 {$src->format('Y-m-d')} 값 설정됨" . PHP_EOL;
        } elseif ($src_result instanceof DateTime) {
            echo "`{$dest}`에 {$src}({$src_result->format('Y-m-d')}) 값 설정됨" . PHP_EOL;
        } elseif (is_string($src) && $src[0] === '$') {
            echo "`{$dest}`에 {$src}({$src_result}) 값 설정됨" . PHP_EOL;
        } else {
            echo "`{$dest}`에 {$src} 값 설정됨" . PHP_EOL;
        }
    }

    private function castVar($src)
    {
        if (is_string($src) && $src[0] === '$') {
            $var_name = substr($src, 1);
            $src = $this->vars[$var_name];
        }
        if (is_object($src)) {
            return clone $src;
        }
        return $src;
    }

    private function commandHtmlType($selector, $val)
    {
        echo "`{$selector}`에 $val 값 설정 시도" . PHP_EOL;
        try {
            if ($element = $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, $selector)) {
                $element->clear();
                $element->sendKeys($val);
                usleep(500 * 1000);
            }
        } catch (PHPWebDriver_NoSuchElementWebDriverError $e) {
            echo '    설정 실패';
        }
    }

    private function commandHtmlClick($selector)
    {
        echo "`{$selector}`를 클릭 시도" . PHP_EOL;
        try {
            if ($element = $this->session->element(PHPWebDriver_WebDriverBy::CSS_SELECTOR, $selector)) {
                $element->click();
                usleep(500 * 1000);
            }
        } catch (PHPWebDriver_NoSuchElementWebDriverError $e) {
            echo '    클릭 실패';
        }
    }

    private function commandWaitDownload($file_name, $download_dir)
    {
        // excel_$loop_start_date:YYYY-MM-DD$.csv
        $result_file_name = preg_replace_callback('/(\$.+)(?::(.+))?\$/U', function ($match) {
            $var = $match[1];
            $format = $match[2];
            $var = $this->castVar($var);
            if ($format) {
                $var = $var->format($format);
            }
            return $var;
        }, $file_name);
        echo "`{$result_file_name}`({$file_name})에 파일 저장 대기" . PHP_EOL;
        foreach (range(1, 100) as $second) {
            $part_files = glob($download_dir . '/*.part');
            $files = glob($download_dir . '/*.*');
            if (count($part_files) === 0 && count($files)) {
                $src = $files[0];
                $dest = __DIR__ . '/../../../' . $result_file_name;
                rename($src, $dest);
                $src_basename = basename($src);
                echo "    파일 저장됨 ({$src_basename}) to ({$result_file_name})" . PHP_EOL;
                break;
            }
            sleep(1);
            echo "    다운로드를 {$second}초째 대기 중" . PHP_EOL;
        }
    }
}
