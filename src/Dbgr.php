<?php

declare(strict_types=1);

namespace noximo;

use Countable;
use Nette\FileNotFoundException;
use Nette\Utils\DateTime;
use Nette\Utils\FileSystem;
use RuntimeException;
use Throwable;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\Helpers;

/**
 * Class Dbgr
 */
class Dbgr
{
    /**
     * @var Dbgr
     */
    private static $instance;

    /** @var bool */
    private static $stylesPrinted;

    /**
     * @var bool
     */
    private static $even = false;

    /** @var ?string */
    private static $color;

    /** @var ?string */
    private static $name;

    /** @var ?string */
    private static $file;

    /**
     * @var bool
     */
    private static $isAjax = false;

    /**
     * @var bool
     */
    private static $forceHTML = false;

    /**
     * @var string[]
     */
    private static $fileOutputs = [];

    /**
     * @var bool
     */
    private static $isConsole = false;

    /** @var string */
    private static $logDir;

    /**
     * @var bool[]
     */
    private static $condition = [];

    /**
     * @var int[]
     */
    private static $counter = [];

    /**
     * @var int[]
     */
    private static $counterTotal = [];

    /**
     * @var mixed[]
     */
    private static $dumperOptions = [];

    /**
     * @var string
     */
    private static $output = '';

    /**
     * @var string[]
     */
    private static $allOutputs = [];

    /**
     * @var float
     */
    private static $firstTimer;

    /**
     * @var float
     */
    private static $lastTimer;

    /** @var int */
    private static $dieAfter;

    /** @var string[] */
    private static $localIPAddresses = ['127.0.0.1', '0.0.0.0', 'localhost', '::1'];

    /** @var bool */
    private static $forceDevelopmentMode = false;
    /** @var string[] */
    private static $allowedIPAddresses = [];

    /** @var string */
    private static $adminerUrlLink;

    /** @var string */
    private static $adminerDatabaseName;
    private static $config;

    public function __construct()
    {
        self::loadDefaultConfig();
    }

    /**
     * @param string|null $file
     */
    public static function loadDefaultConfig(): void
    {
        $defaultFile = FileSystem::read(__DIR__ . DIRECTORY_SEPARATOR);
        self::$config = json_decode($defaultFile);

        $localFile = \dirname(__FILE__, 3) . DIRECTORY_SEPARATOR . 'dbgr.json';
        $customConfig = [];
        if (file_exists($localFile)) {
            $content = FileSystem::read($localFile);
            $customConfig = json_decode($content);
        }

        self::setConfig($customConfig);
    }

    /**
     * @param array $customConfig
     */
    public static function setConfig(array $customConfig): self
    {
        self::$config = array_merge_recursive(self::$config, $customConfig);

        self::$logDir = self::$config['logDir'] . DIRECTORY_SEPARATOR;
        self::$allowedIPAddresses = self::$config['allowedIPAddresses'] ?? null;
        self::$adminerUrlLink = self::$config['adminerUrlLink'] ?? null;
        self::$adminerDatabaseName = self::$config['adminerDatabaseName'] ?? null;

        if (isset(self::$config['editorUri']) &&
            self::$config['editorUri'] !== null &&
            self::$config['editorUri'] !== '' &&
            self::$config['editorUri'] !== Debugger::$editor) {
            /** @noinspection DisallowWritingIntoStaticPropertiesInspection */
            Debugger::$editor = self::$config['editorUri'];
        }

        return self::getInstance();
    }

    /**
     * @return Dbgr
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param $file
     *
     * @return Dbgr
     */
    public static function loadConfig($file): Dbgr
    {
        if (file_exists($file)) {
            $content = FileSystem::read($file);
            $customConfig = json_decode($content);

            self::setConfig($customConfig);

            return self::getInstance();
        }

        throw new FileNotFoundException('Configuration file ' . $file . ' not found');
    }

    /**
     * Set name for debug
     *
     * @param string $name
     *
     * @return Dbgr
     */
    public static function setName(string $name): self
    {
        self::$name = $name;

        return self::getInstance();
    }

    /**
     * Nastaví do jaké hloubky se mají vypsat proměnné
     *
     * @param int $depth
     *
     * @return Dbgr
     */
    public static function setDepth(int $depth): self
    {
        self::defaultOptions();
        self::$dumperOptions[Dumper::DEPTH] = $depth;

        return self::getInstance();
    }

    /**
     * Nastaví výchozí nastavení
     *
     * @param bool $reset
     */
    public static function defaultOptions(bool $reset = false): void
    {
        if ($reset || empty(self::$dumperOptions)) {
            self::$dumperOptions = [
                Dumper::DEPTH => 4,
                Dumper::TRUNCATE => 1024,
                Dumper::COLLAPSE => false,
                Dumper::COLLAPSE_COUNT => 15,
                Dumper::DEBUGINFO => true,
                Dumper::LOCATION => Dumper::LOCATION_CLASS,
            ];
            self::$forceDevelopmentMode = false;
        }
    }

    /**
     * Output dump into file. Multiple calls with same filename will be merged into one file.
     *
     * @param string $filename
     *
     * @return Dbgr
     */
    public static function setFile(string $filename = null): self
    {
        if ($filename === null) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $params = self::getParams($backtrace);
            $filename = self::getHash([], $backtrace, $params);
        }
        $filename = str_replace('.html', '', $filename) . '.html';
        self::$file = $filename;
        self::forceHtml();

        return self::getInstance();
    }

    /**
     * @param array $backtrace
     *
     * @return string[]
     */
    private static function getParams(array $backtrace): array
    {
        $file = file($backtrace[0]['file']);
        $line = trim((string) $file[$backtrace[0]['line'] - 1]);

        $start = strpos($line, 'dump(') + 5;

        $stop = strpos($line, ');', $start);

        $params = substr($line, $start, $stop - $start);

        return explode(',', $params);
    }

    /**
     * @param string[] $args
     * @param string[] $backtrace
     * @param string[] $params
     *
     * @return string
     */
    private static function getHash(array $args, array $backtrace, array $params): string
    {
        return md5(base64_encode((string) json_encode([$args, $backtrace, $params])));
    }

    /**
     * Should dumped data always print out formatted as HTML?
     *
     * @param bool $set Vypsat?
     *
     * @return Dbgr
     */
    public static function forceHtml(bool $set = true): self
    {
        self::$forceHTML = $set;

        return self::getInstance();
    }

    /**
     * End script execution instantly and loudly.
     *
     * @param bool $force
     */
    public static function dieNow(bool $force = false): void
    {
        if ($force || self::canBeOutputed()) {
            self::setColor('red');
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $params = self::getParams($backtrace);
            self::debugProccess(['SCRIPT FORCEFULLY ENDED'], $backtrace, $params);

            die();
        }
    }

    /**
     * @return bool
     */
    public static function canBeOutputed(): bool
    {
        $addresses = array_merge(self::$localIPAddresses, self::$allowedIPAddresses);

        return self::$forceDevelopmentMode || Debugger::detectDebugMode($addresses);
    }

    /**
     * Nastaví barvu výpisu
     *
     * @param string $color
     *
     * @return Dbgr
     */
    public static function setColor(string $color): self
    {
        self::$color = $color;

        return self::getInstance();
    }

    /**
     * @param array $args
     * @param array $backtrace
     * @param array $params
     */
    private static function debugProccess(array $args, array $backtrace, array $params): void
    {
        self::clearOutput();
        self::debugStart(self::getHash($args, $backtrace, $params));

        self::firstBacktrace($backtrace);
        if (!self::$isAjax && !self::$isConsole) {
            self::restOftheBacktraces($backtrace);
        }

        self::printVariables($args, $params);

        self::debugEnd();
        self::printOutput();
    }

    private static function clearOutput(): void
    {
        self::$output = '';
    }

    /**
     * @param string $hash
     */
    private static function debugStart(string $hash): void
    {
        if (self::$forceHTML === false &&
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            self::$isAjax = true;
        }

        if (PHP_SAPI === 'cli') {
            self::$isConsole = true;
        }

        $color = self::$even ? 'lightyellow' : 'rgb(255, 255, 187)';
        self::$even = !self::$even;

        $borderColor = self::colorize();

        self::defaultOptions();
        self::addToOutput("<div class='debug-inline hash-" . $hash . "' style='background-color:" . $color . '; border-left: 6px double ' . $borderColor . ";'>");
    }

    /**
     * @return string
     */
    private static function colorize(): string
    {
        $backtrace = base64_encode(serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));

        $code = dechex(crc32($backtrace));
        $code = substr($code, 0, 6);

        return '#' . $code;
    }

    /**
     * @param string $output
     * @param string|null $endofline
     */
    private static function addToOutput(string $output, ?string $endofline = PHP_EOL): void
    {
        self::$output .= $output . $endofline;
    }

    /**
     * @param string[] $backtrace
     */
    private static function firstBacktrace(array $backtrace): void
    {
        $first = self::getFirstBacktrace($backtrace);
        $color = null;
        if (self::$color) {
            $color = 'style = "background-color:' . self::$color . ';"';
            self::$color = null;
        }
        self::addToOutput('<div ondblclick="debugToggle(this);" class="debug-backtrace debug-backtrace-first" title="Dvojklikem zobraz podrobnosti" ' . $color . '>');
        if (self::$name) {
            self::addToOutput("<div class='debug-inline-name'>" . self::$name . '</div>');
            self::$name = null;
        }
        self::printHeader($first, true);
        self::addToOutput('</div>');
    }

    /**
     * @param string[] $backtrace
     *
     * @return mixed
     */
    private static function getFirstBacktrace(array $backtrace)
    {
        $first = $backtrace[0];
        if (isset($backtrace[1]['class'])) {
            $first['class'] = $backtrace[1]['class'];
        }

        if (isset($backtrace[1]['function'])) {
            $first['function'] = $backtrace[1]['function'];
        }

        if (isset($backtrace[1]['type'])) {
            $first['type'] = $backtrace[1]['type'];
        }

        return $first;
    }

    /**
     * @param array $backtrace
     * @param bool|null $first
     */
    private static function printHeader(array $backtrace, ?bool $first = null): void
    {
        $first = $first ?? null;
        $line = self::printBacktrace($backtrace);

        if ($first) {
            $nowMicro = microtime(true);
            $now = DateTime::createFromFormat('U.u', (string) $nowMicro);

            if (self::$firstTimer) {
                $difference = str_pad((string) round($nowMicro - self::$firstTimer, 3), 5, '0');
                $lastDifference = str_pad((string) round($nowMicro - self::$lastTimer, 3), 5, '0');
                if ($now instanceof DateTime) {
                    $line .= "<span class='debug-hide' title='" . $now->format('Y-m-d H:i:s:u') . "'>" . $difference . ' (' . $lastDifference . ')</span>';
                }
            } else {
                self::$firstTimer = $nowMicro;
                if ($now instanceof DateTime) {
                    $line .= "<span class='debug-hide' title='" . $now->format('Y-m-d H:i:s:u') . "'>" . $now->format('H:i:s:u') . '</span>';
                }
            }
            self::$lastTimer = $nowMicro;
        }

        self::addToOutput($line);
    }

    /**
     * @param string[] $backtrace
     *
     * @return string
     */
    private static function printBacktrace(array $backtrace): string
    {
        $line = '';
        if (isset($backtrace['file'])) {
            $line .= self::getOpenInIDEBacktrace($backtrace);
        }

        if (isset($backtrace['class'])) {
            $line .= $backtrace['class'] . $backtrace['type'];
        }

        if (isset($backtrace['function'])) {
            $line .= $backtrace['function'] . '() ';
        }

        return $line;
    }

    /**
     * @param string[] $backtrace
     *
     * @return string
     */
    private static function getOpenInIDEBacktrace(array $backtrace): string
    {
        $link = self::getOpenInIDELink($backtrace['file'], (int) $backtrace['line']);
        $line = "<a title='Otevřít v editoru' href='" . $link . "'><small>" . \dirname($backtrace['file']) . DIRECTORY_SEPARATOR . '</small><strong>' . basename($backtrace['file']);

        if (isset($backtrace['line'])) {
            $line .= ' (' . $backtrace['line'] . ')';
        }

        $line .= '</strong></a> ';

        return $line;
    }

    /**
     * @param string $file
     * @param int $line
     *
     * @return string
     */
    private static function getOpenInIDELink(string $file, int $line): string
    {
        return Helpers::editorUri($file, $line) ?? '#';
    }

    /**
     * @param array $backtrace
     */
    private static function restOftheBacktraces(array $backtrace): void
    {
        self::addToOutput('<div class="debug-backtraces" style="display:none;" >');
        /** @var int $i */
        for ($i = count($backtrace) - 1; $i >= 0; $i--) {
            $param = $backtrace[$i];
            self::addToOutput("<div class='debug-backtrace'>");
            if ($i + 1 > 0) {
                self::addToOutput($i + 1 . '. ');
            }
            self::printHeader($param);
            self::addToOutput('</div>');
        }

        if (!empty($_GET)) {
            self::printVariables([$_GET], ['GET']);
        }
        if (!empty($_POST)) {
            self::printVariables([$_POST], ['POST']);
        }
        if (!empty($_SERVER)) {
            self::printVariables([$_SERVER], ['SERVER']);
        }
        if (!empty($_SESSION)) {
            self::printVariables([$_SESSION], ['SESSION']);
        }

        self::printVariables([PHP_VERSION], ['PHP Version']);

        self::addToOutput('</div>');
    }

    /**
     * @param mixed[] $variables
     * @param mixed[] $params
     */
    private static function printVariables(array $variables, array $params): void
    {
        foreach ($variables as $key => $variable) {
            if ((self::$isAjax || self::$isConsole) && !self::$file) {
                self::addToOutput('---');
            }
            self::addToOutput("<div><strong class='debug-variable-name'>" . $params[$key] . ':</strong>');
            self::addToOutput("<pre ondblclick='debugExpand(this);' class='debug-variable'>");

            if (\is_string($variable) && self::isSQL($variable)) {
                self::addToOutput("<div class='debug-sql'>");
                self::addToOutput(self::highlight($variable));
                self::addToOutput(self::sqlLink($variable));
                self::addToOutput('</div>');
            } elseif ($variable instanceof Throwable) {
                /** @var Throwable $variable */
                self::addToOutput(self::useDumper($variable), null);
                self::printBacktraces($variable->getTrace());
            } elseif (self::isBacktrace($variable)) {
                self::printBacktraces($variable);
            } else {
                self::addToOutput(self::useDumper($variable), null);
            }
            self::addToOutput('</pre></div>');
        }
    }

    /**
     * @param string $sql
     *
     * @return bool
     */
    private static function isSQL(string $sql): bool
    {
        $keywords1 = 'CREATE\s+TABLE|CREATE(?:\s+UNIQUE)?\s+INDEX|SELECT|SHOW|TABLE|STATUS|FULL|COLUMNS|JOIN|UPDATE|INSERT(?:\s+INTO)?|REPLACE(?:\s+INTO)?|DELETE|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|SET|VALUES|LEFT\s+JOIN|INNER\s+JOIN|TRUNCATE';
        $keywords2 = 'ALL|DISTINCT|DISTINCTROW|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|LIKE|TRUE|FALSE|INTEGER|CLOB|VARCHAR|DATETIME|TIME|DATE|INT|SMALLINT|BIGINT|BOOL|BOOLEAN|DECIMAL|FLOAT|TEXT|VARCHAR|DEFAULT|AUTOINCREMENT|DESC|PRIMARY\s+KEY';

        $patter = "#(/\\*.+?\\*/)|(\\*\\*.+?\\*\\*)|(?<=[\\s,(])(${keywords1})(?=[\\s,)])|(?<=[\\s,(=])(${keywords2})(?=[\\s,)=])#si";
        preg_match($patter, strtoupper($sql), $matches);
        if (!empty($matches)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $sql
     *
     * @return string
     */
    private static function highlight(string $sql): string
    {
        $keywords1 = 'CREATE\s+TABLE|CREATE(?:\s+UNIQUE)?\s+INDEX|SELECT|SHOW|TABLE|STATUS|FULL|COLUMNS|JOIN|UPDATE|INSERT(?:\s+INTO)?|REPLACE(?:\s+INTO)?|DELETE|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|SET|VALUES|LEFT\s+JOIN|INNER\s+JOIN|TRUNCATE';
        $keywords2 = 'ALL|DISTINCT|DISTINCTROW|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|LIKE|TRUE|FALSE|INTEGER|CLOB|VARCHAR|DATETIME|TIME|DATE|INT|SMALLINT|BIGINT|BOOL|BOOLEAN|DECIMAL|FLOAT|TEXT|VARCHAR|DEFAULT|AUTOINCREMENT|DESC|PRIMARY\s+KEY';
        $break = '<br>';
        // insert new lines - too dizzy
        $sql = " ${sql} ";

        // reduce spaces
        $sql = wordwrap($sql, 100);
        $sql = (string) preg_replace("#([ \t]*\r?\n){2,}#", "\n", $sql);
        $sql = (string) preg_replace('#VARCHAR\\(#', 'VARCHAR (', $sql);
        $sql = str_replace('            ', ' ', $sql);

        // syntax highlight
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $pattern = "#(/\\*.+?\\*/)|(\\*\\*.+?\\*\\*)|(?<=[\\s,(])(${keywords1})(?=[\\s,)])|(?<=[\\s,(=])(${keywords2})(?=[\\s,)=])#si";
        $sql = (string) preg_replace_callback($pattern, function ($matches) use ($break) {
            if (!empty($matches[1])) {
                // comment
                return '<em style="color:gray">' . $matches[1] . '</em>';
            }

            if (!empty($matches[2])) {
                // error
                return '<strong style="color:red">' . $matches[2] . '</strong>';
            }

            if (!empty($matches[3])) {
                // most important keywords
                return $break . '<strong style="color:blue">' . strtoupper($matches[3]) . '</strong>';
            }

            if (!empty($matches[4])) {
                // other keywords
                return '<strong style="color:green">' . strtoupper($matches[4]) . '</strong>';
            }

            return '';
        }, $sql);
        $sql = trim((string) preg_replace('#' . preg_quote($break, '/') . '#', '', $sql, 1));

        return "<span class='dump'>${sql}</span>";
    }

    /**
     * @param string $sql
     *
     * @return string
     */
    private static function sqlLink(string $sql): string
    {
        $return = '';
        if (self::$adminerDatabaseName !== null && self::$adminerUrlLink !== null && self::canBeOutputed()) {
            $query = [
                'username' => 'develop',
                'db' => self::$adminerDatabaseName,
                'sql' => trim((string) preg_replace('/[ \t]+/', ' ', $sql)),
            ];
            $return = '<a class="debug-sql-link" target="_blank" href="' . self::$adminerUrlLink . '?' . http_build_query($query) . '">Otevřít v admineru</a>';
        }

        return $return;
    }

    /**
     * @param mixed $variable
     *
     * @return mixed
     */
    private static function useDumper($variable)
    {
        $options = self::$dumperOptions;
        if (\is_string($variable)) {
            $options[Dumper::TRUNCATE] = false;
        }

        if (PHP_SAPI !== 'cli' && !preg_match('#^Content-Type: (?!text/html)#im', implode("\n", headers_list()))) {
            $string = Dumper::toHtml($variable, $options);
        } elseif (self::detectColors()) {
            $string = Dumper::toTerminal($variable, $options);
        } else {
            $string = Dumper::toText($variable, $options);
        }

        return $string;
    }

    /**
     * @return bool
     */
    private static function detectColors(): bool
    {
        return Dumper::$terminalColors &&
            (getenv('ConEmuANSI') === 'ON'
                || getenv('ANSICON') !== false
                || getenv('term') === 'xterm-256color'
                || (\defined('STDOUT') && \function_exists('posix_isatty') && posix_isatty(STDOUT)));
    }

    /**
     * @param string[][] $backtraces
     */
    private static function printBacktraces(array $backtraces): void
    {
        self::addToOutput('<div class="debug-backtrace-as-variable">');
        foreach ($backtraces as $i => $backtrace) {
            self::addToOutput("<div class='debug-backtrace'>", null);
            self::addToOutput($i + 1 . '. ', null);
            self::printHeader($backtrace);
            self::addToOutput('</div>', null);
        }
        self::addToOutput('</div>');
    }

    /**
     * @param mixed $variable
     *
     * @return bool
     */
    private static function isBacktrace($variable): bool
    {
        return \is_array($variable) &&
            (isset($variable[0]['function']) || isset($variable[0]['file'], $variable[0]['line']));
    }

    private static function debugEnd(): void
    {
        self::defaultOptions(true);
        self::addToOutput('</div>');
    }

    private static function printOutput(): void
    {
        self::$allOutputs[] = self::$output;

        if (!self::$isConsole && empty(self::$file) && self::canBeOutputed()) {
            if (!self::$stylesPrinted && !self::$isAjax) {
                self::printStyles();
            }
            if (self::$isAjax) {
                self::echo(self::ajaxOutput() . '===========================' . PHP_EOL, false, false, true);
            } else {
                self::echo(self::$output, false);
            }
        } elseif (!empty(self::$file) && !empty(self::$logDir)) {
            if (!isset(self::$fileOutputs[self::$file])) {
                self::$fileOutputs[self::$file] = '';
            }
            self::$fileOutputs[self::$file] .= self::$output;

            FileSystem::write(self::$logDir . self::$file, self::getHTML(self::$fileOutputs[self::$file]));
            self::$file = null;
        } elseif (self::$isConsole) {
            $str = PHP_EOL . '..................................' . PHP_EOL;
            self::echo(PHP_EOL . $str . self::ajaxOutput() . $str . PHP_EOL);
        }
    }

    private static function printStyles(): void
    {
        self::addToOutput(self::getStyles());
        self::$stylesPrinted = true;
    }

    /**
     * @return string
     */
    private static function getStyles(): string
    {
        $styles = '';
        $path = __DIR__ . DIRECTORY_SEPARATOR;
        $styles .= '<style>';
        $styles .= FileSystem::read($path . 'dumper.css');
        $styles .= '</style>';
        $styles .= '<script>';
        $styles .= FileSystem::read($path . 'dumper.js');
        $styles .= '</script>';

        return $styles;
    }

    /**
     * Instantly prints out message
     *
     * @param string $message
     * @param bool $bold Should message be bolder?
     * @param bool $showTime
     * @param bool $stripTags
     *
     * @return Dbgr
     */
    public static function echo(string $message, bool $bold = true, bool $showTime = false, bool $stripTags = false): self
    {
        if ($bold) {
            $message = '<b>' . $message . '</b>';
        }
        if ($showTime) {
            $message = date('Y-m-d H:i:s') . ' - ' . $message;
        }
        if (PHP_SAPI !== 'cli') {
            echo $message;
        } elseif ($stripTags) {
            echo strip_tags($message);
        } else {
            echo $message;
        }
        echo PHP_EOL;
        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        @flush();
        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        @ob_flush();

        return self::getInstance();
    }

    /**
     * @return mixed
     */
    private static function ajaxOutput()
    {
        if (\is_string(self::$output)) {
            $pregReplace = (string) preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", strip_tags(self::$output));

            return str_replace('x//', '', $pregReplace);
        }

        return '';
    }

    /**
     * @param string $lines
     *
     * @return string
     */
    private static function getHTML(string $lines): string
    {
        $html = '';
        $html .= '<html lang=""><body>';
        $html .= str_replace(PHP_EOL, ' ', self::getStyles()) . PHP_EOL;
        $html .= $lines;
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Stop script execution after $count calls. Can output variable
     *
     * @param int $count
     * @param bool $force
     * @param mixed $variable
     *
     * @return Dbgr
     */
    public static function dieAfter(int $count, bool $force = false, $variable = 'not_set'): self
    {
        if ($force || self::canBeOutputed()) {
            if (self::$dieAfter === null) {
                self::$dieAfter = $count - 1;
            } else {
                self::$dieAfter--;
                if (self::$dieAfter === 0) {
                    if ($variable !== 'not_set') {
                        self::dump($variable);
                    }
                    self::setColor('red');
                    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                    self::debugProccess(['END' => 'SCRIPT FORCEFULLY STOPPED AFTER' . $count . ' CALLS'], $backtrace, ['END' => 'END']);

                    die();
                }
            }
        }

        return self::getInstance();
    }

    /**
     * Dump any number of variables
     *
     * @param mixed[] $variables
     *
     * @return Dbgr
     */
    public static function dump(...$variables): self
    {
        self::defaultOptions();
        $backtrace = debug_backtrace();
        $params = self::getParams($backtrace);

        self::debugProccess($variables, $backtrace, $params);

        return self::getInstance();
    }

    /**
     * @param bool $set
     *
     * @return Dbgr
     */
    public static function noAjax(bool $set = true): self
    {
        return self::forceHtml($set);
    }

    /**
     * Sets where setFile will write output
     *
     * @param string $logDir
     *
     * @return Dbgr
     */
    public static function setLogDir(string $logDir): self
    {
        self::$logDir = rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        FileSystem::createDir(self::$logDir);

        return self::getInstance();
    }

    /**
     * Dump only if previously set condition is true. Use method condition to set up condition
     *
     * @param string $conditionName
     * @param mixed[] $args
     *
     * @return Dbgr
     */
    public static function dumpConditional(string $conditionName, ...$args): self
    {
        if (isset(self::$condition[$conditionName]) && self::$condition[$conditionName]) {
            self::defaultOptions();
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $params = self::getParams($backtrace);

            self::debugProccess($args, $backtrace, $params);
        }

        return self::getInstance();
    }

    /**
     * Dumps only if first parameter is true. Use condition() and dumpConditional() for better versatility
     *
     * @param bool $condition
     * @param mixed[] ...$args
     *
     * @return Dbgr
     */
    public static function dumpOnTrue(bool $condition, ...$args): self
    {
        if ($condition === true) {
            self::defaultOptions();
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $params = self::getParams($backtrace);

            self::debugProccess($args, $backtrace, $params);
        }

        return self::getInstance();
    }

    /**
     * alias of condition
     *
     * @param string $conditionName
     * @param bool $value
     *
     * @return Dbgr
     */
    public static function setCondition(string $conditionName, bool $value): self
    {
        return self::condition($conditionName, $value);
    }

    /**
     * Set condition to control dumpConditional calls
     *
     * @param string $conditionName
     * @param bool $value
     *
     * @return Dbgr
     */
    public static function condition(string $conditionName, bool $value): self
    {
        self::$condition[$conditionName] = $value;

        return self::getInstance();
    }

    /**
     * Set new counter. Use before incrementCounter
     *
     * @param string $name Name of the counter
     * @param int|mixed[]|Countable $count Total count
     *
     * @return Dbgr
     * @throws RuntimeException
     */
    public static function setCounter(string $name, $count): self
    {
        if ($count instanceof Countable || \is_array($count)) {
            $count = count($count);
        }

        if (!\is_int($count)) {
            throw new RuntimeException('Argument is not countable');
        }

        self::$counter[$name] = 0;
        self::$counterTotal[$name] = $count;

        return self::getInstance();
    }

    /**
     * Increments counter. Use after counter is set using setCounter
     *
     * @param string $name Counter to increment
     * @param int $printAfter After how many increments should be counter printed? (0 = never)
     *
     * @return Dbgr
     */
    public static function incrementCounter(string $name, int $printAfter = 1): self
    {
        $count = self::$counter[$name]++;
        if ($printAfter && $count % $printAfter === 0) {
            self::echo($count . '/' . self::$counterTotal[$name] . ' (' . $name . ')');
        }

        return self::getInstance();
    }

    /**
     * @param bool $forceDevelopmentMode
     *
     * @return Dbgr
     */
    public static function forceDevelopmentMode(bool $forceDevelopmentMode = true): Dbgr
    {
        self::$forceDevelopmentMode = $forceDevelopmentMode;

        return self::getInstance();
    }
}
