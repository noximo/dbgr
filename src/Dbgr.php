<?php

declare(strict_types=1);

namespace noximo;

use Countable;
use Throwable;
use Tracy\Dumper;
use Tracy\Helpers;
use Tracy\Debugger;
use Nette\Utils\Json;
use RuntimeException;
use Nette\Utils\DateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\JsonException;
use Nette\FileNotFoundException;
use function is_int;
use function defined;
use function dirname;
use function is_array;
use function is_string;
use function function_exists;

/**
 * Class Dbgr
 */
final class Dbgr
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

    /** @var string */
    private static $adminerUsername;

    /** @var mixed[] */
    private static $config;

    /** @var bool */
    private static $initialized = false;

    /** @var string */
    private static $rootDir;

    /**
     * Dbgr constructor.
     */
    public function __construct()
    {
        self::loadDefaultConfig();
    }

    public function __toString(): string
    {
        return '';
    }

    public static function loadDefaultConfig(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$rootDir = realpath(dirname(__DIR__, 4) . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        try {
            $defaultFile = FileSystem::read(__DIR__ . DIRECTORY_SEPARATOR . 'dbgr.config.json');
            self::$config = Json::decode($defaultFile, Json::FORCE_ARRAY);
        } catch (Throwable $e) {
            self::echo("Default config couldn't be inicialized: " . $e->getMessage());
        }

        $localFile = self::$rootDir . DIRECTORY_SEPARATOR . 'dbgr.json';
        $customConfig = [];
        if (file_exists($localFile)) {
            try {
                $content = FileSystem::read($localFile);
                $customConfig = Json::decode($content, Json::FORCE_ARRAY);
            } catch (Throwable $e) {
                self::echo("Local config couldn't be inicialized: " . $e->getMessage());
            }
        }

        self::setConfigData($customConfig);
        self::$initialized = true;
    }

    /**
     * Instantly prints out message
     *
     * @param bool $bold Should message be bolder?
     *
     * @return Dbgr
     */
    public static function echo(string $message, bool $bold = true, bool $showTime = false, bool $stripTags = false): self
    {
        self::loadDefaultConfig();

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
     * @return Dbgr
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function setProperLogDir(string $logDir): void
    {
        $logDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logDir);
        if (FileSystem::isAbsolute($logDir)) {
            $path = $logDir;
        } else {
            $path = self::$rootDir . trim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        self::$logDir = $path;
        FileSystem::createDir(self::$logDir);
    }

    /**
     * Instantly prints out message
     *
     * @param mixed[] $customConfig
     *
     * @return Dbgr
     */
    public static function setConfig(array $customConfig): self
    {
        self::loadDefaultConfig();

        self::setConfigData($customConfig);

        return self::getInstance();
    }

    /**
     * @return Dbgr
     * @throws FileNotFoundException
     */
    public static function loadConfig(string $filefilename): self
    {
        self::loadDefaultConfig();

        if (file_exists($filefilename)) {
            $customConfig = [];
            try {
                $content = FileSystem::read($filefilename);
                $customConfig = Json::decode($content, Json::FORCE_ARRAY);
            } catch (Throwable $e) {
                self::echo("Config couldn't be inicialized: " . $e->getMessage());
            }

            self::setConfigData($customConfig);

            return self::getInstance();
        }

        throw new FileNotFoundException('Configuration file ' . $filefilename . ' not found');
    }

    /**
     * Set name for debug
     * @return Dbgr
     */
    public static function setName(string $name): self
    {
        self::loadDefaultConfig();

        self::$name = $name;

        return self::getInstance();
    }

    /**
     * Nastaví do jaké hloubky se mají vypsat proměnné
     * @return Dbgr
     */
    public static function setDepth(int $depth): self
    {
        self::loadDefaultConfig();

        self::defaultOptions();
        self::$dumperOptions[Dumper::DEPTH] = $depth;

        return self::getInstance();
    }

    /**
     * Nastaví výchozí nastavení
     */
    public static function defaultOptions(bool $reset = false): void
    {
        self::loadDefaultConfig();

        if ($reset || empty(self::$dumperOptions)) {
            self::$dumperOptions = [
                Dumper::DEPTH => 4,
                Dumper::TRUNCATE => 1024,
                Dumper::COLLAPSE => 14,
                Dumper::COLLAPSE_COUNT => 7,
                Dumper::DEBUGINFO => true,
                Dumper::LOCATION => Dumper::LOCATION_CLASS,
            ];
            self::$forceDevelopmentMode = false;
        }
    }

    /**
     * Output dump into file. Multiple calls with same filename will be merged into one file.
     *
     * @param string|null $filename
     *
     * @return Dbgr
     */
    public static function setFile(?string $filename = null): self
    {
        self::loadDefaultConfig();

        if ($filename === null) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $filename = self::getHash($backtrace);
        }
        $filename = str_replace('.html', '', $filename) . '.html';
        self::$file = $filename;
        self::forceHtml();

        return self::getInstance();
    }

    /**
     * Should dumped data always print out formatted as HTML?
     * @return Dbgr
     */
    public static function forceHtml(bool $set = true): self
    {
        self::loadDefaultConfig();
        self::$forceHTML = $set;

        return self::getInstance();
    }

    /**
     * End script execution instantly and loudly.
     */
    public static function dieNow(bool $force = false): void
    {
        self::loadDefaultConfig();

        if ($force || self::canBeOutputed()) {
            self::setColor('red');
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            self::debugProccess(['SCRIPT FORCEFULLY ENDED'], $backtrace);

            die();
        }
    }

    public static function canBeOutputed(): bool
    {
        self::loadDefaultConfig();

        return self::$forceDevelopmentMode || Debugger::isEnabled() || Debugger::detectDebugMode(array_merge(self::$localIPAddresses, self::$allowedIPAddresses));
    }

    /**
     * Nastaví barvu výpisu
     * @return Dbgr
     */
    public static function setColor(string $color): self
    {
        self::loadDefaultConfig();
        self::$color = $color;

        return self::getInstance();
    }

    /**
     * @param mixed[] $args
     * @param mixed[] $backtrace
     * @param string[] $params
     * @internal Call dump instead
     */
    public static function debugProccess(array $args, array $backtrace, ?array $params = null): void
    {
        self::clearOutput();
        if ($params === null) {
            $params = self::getParams($backtrace);
        }
        self::debugStart(self::getHash($backtrace));

        self::firstBacktrace($backtrace);
        if ((!self::$isAjax && !self::$isConsole) || self::$forceHTML === true) {
            self::restOftheBacktraces($backtrace);
        }

        self::printVariables($args, $params);

        self::printDidYouKnow();
        self::debugEnd();
        self::printOutput();
    }

    /**
     * Stop script execution after $count calls. Can output variable
     *
     * @param mixed $variable
     *
     * @return Dbgr
     */
    public static function dieAfter(int $count, bool $force = false, $variable = 'not_set'): self
    {
        self::loadDefaultConfig();

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
     * @param mixed ...$variables
     *
     * @return Dbgr
     */
    public static function dump(...$variables): self
    {
        self::loadDefaultConfig();
        self::defaultOptions();

        $backtrace = debug_backtrace();
        $params = null;
        if (empty($variables)) {
            $variables = [$backtrace];
            $params = ['backtrace'];
        }
        self::debugProccess($variables, $backtrace, $params);

        return self::getInstance();
    }

    /**
     * Sets where setFile will write output
     * @return Dbgr
     */
    public static function setLogDir(string $logDir): self
    {
        self::loadDefaultConfig();

        self::setProperLogDir($logDir);

        return self::getInstance();
    }

    /**
     * Dump only if previously set condition is true. Use method condition to set up condition
     *
     * @param mixed ...$args
     *
     * @return Dbgr
     */
    public static function dumpConditional(string $conditionName, ...$args): self
    {
        self::loadDefaultConfig();

        if (isset(self::$condition[$conditionName]) && self::$condition[$conditionName]) {
            self::defaultOptions();
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            self::debugProccess($args, $backtrace);
        }

        return self::getInstance();
    }

    /**
     * Dumps only if first parameter is true. Use condition() and dumpConditional() for better versatility
     *
     * @param mixed ...$args
     *
     * @return Dbgr
     */
    public static function dumpOnTrue(bool $condition, ...$args): self
    {
        self::loadDefaultConfig();

        if ($condition === true) {
            self::defaultOptions();
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            self::debugProccess($args, $backtrace);
        }

        return self::getInstance();
    }

    /**
     * alias of condition
     * @return Dbgr
     */
    public static function setCondition(string $conditionName, bool $value): self
    {
        self::loadDefaultConfig();

        return self::condition($conditionName, $value);
    }

    /**
     * Set condition to control dumpConditional calls
     * @return Dbgr
     */
    public static function condition(string $conditionName, bool $value): self
    {
        self::loadDefaultConfig();

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
     */
    public static function setCounter(string $name, $count): self
    {
        self::loadDefaultConfig();

        if ($count instanceof Countable || is_array($count)) {
            $count = count($count);
        }

        if (!is_int($count)) {
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
        self::loadDefaultConfig();

        $count = self::$counter[$name]++;
        if ($printAfter && $count % $printAfter === 0) {
            self::echo($count . '/' . self::$counterTotal[$name] . ' (' . $name . ')');
        }

        return self::getInstance();
    }

    /**
     * @return Dbgr
     */
    public static function forceDevelopmentMode(bool $forceDevelopmentMode = true): self
    {
        self::loadDefaultConfig();

        self::$forceDevelopmentMode = $forceDevelopmentMode;

        return self::getInstance();
    }

    /**
     * @param mixed[] $customConfig
     */
    private static function setConfigData(array $customConfig): void
    {
        self::$config = (array) array_replace_recursive(self::$config, $customConfig);

        self::setProperLogDir(self::$config['logDir']);
        self::$allowedIPAddresses = self::$config['allowedIPAddresses'] ?? null;
        self::$adminerUrlLink = self::$config['adminerUrlLink'] ?? null;
        self::$adminerDatabaseName = self::$config['adminerDatabaseName'] ?? null;
        self::$adminerUsername = self::$config['adminerUsername'] ?? null;

        if (isset(self::$config['editorUri']) &&
            self::$config['editorUri'] !== null &&
            self::$config['editorUri'] !== '' &&
            self::$config['editorUri'] !== Debugger::$editor) {
            /** @noinspection DisallowWritingIntoStaticPropertiesInspection */
            Debugger::$editor = self::$config['editorUri'];
        }
    }

    /**
     * @param mixed[] $backtrace
     * @return string
     */
    private static function getHash(array $backtrace): string
    {
        try {
            $array = Json::encode([array_column($backtrace, 'file'), array_column($backtrace, 'line')]);

            return md5($array);
        } catch (JsonException $e) {
            return md5('');
        }
    }

    private static function clearOutput(): void
    {
        self::$output = '';
    }

    /**
     * @param mixed[] $backtrace
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

    private static function colorize(): string
    {
        $backtrace = base64_encode(serialize(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));

        $code = dechex(crc32($backtrace));
        $code = substr($code, 0, 6);

        return '#' . $code;
    }

    /**
     * @param string|null $endofline
     */
    private static function addToOutput(string $output, ?string $endofline = PHP_EOL): void
    {
        self::$output .= $output . $endofline;
    }

    /**
     * @param mixed[] $backtrace
     */
    private static function firstBacktrace(array $backtrace): void
    {
        $first = self::getFirstBacktrace($backtrace);
        $color = null;
        if (self::$color) {
            $color = 'style = "background-color:' . self::$color . ';"';
            self::$color = null;
        }
        self::addToOutput('<div onclick="debugToggle(this, event);" class="debug-backtrace debug-backtrace-first" title="Hold CTRL and double-click or triple-click to show more info" ' . $color . '>');
        if (self::$name) {
            self::addToOutput("<div class='debug-inline-name'>" . self::$name . '</div>');
            self::$name = null;
        }
        self::printHeader($first, true);
        self::addToOutput('</div>');
    }

    private static function getFirstBacktrace(array $backtrace): array
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

            /** @noinspection PhpUnhandledExceptionInspection */
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
     * @param mixed[] $backtrace
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
     * @param mixed[] $backtrace
     */
    private static function getOpenInIDEBacktrace(array $backtrace): string
    {
        $link = self::getOpenInIDELink($backtrace['file'], (int) $backtrace['line']);
        $line = "<a title='Otevřít v editoru' href='" . $link . "'><small>" . dirname($backtrace['file']) . DIRECTORY_SEPARATOR . '</small><strong>' . basename($backtrace['file']);

        if (isset($backtrace['line'])) {
            $line .= ' (' . $backtrace['line'] . ')';
        }

        $line .= '</strong></a> ';

        return $line;
    }

    private static function getOpenInIDELink(string $file, int $line): string
    {
        return Helpers::editorUri($file, $line) ?? '#';
    }

    /**
     * @param mixed[] $backtrace
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
        self::printVariables([self::$config], ['Current configuration']);

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
            self::addToOutput("<div onclick='debugExpand(this, event);' class='debug-variable' title='Hold CTRL and double-click or triple-click to enlarge/shrink this dump'>");

            if (is_string($variable) && self::isSQL($variable)) {
                self::addToOutput("<div class='debug-sql'>");
                self::addToOutput(self::highlight($variable));
                self::addToOutput(self::sqlLink($variable));
                self::addToOutput('</div>');
            } elseif ($variable instanceof Throwable) {
                self::addToOutput(self::useDumper($variable), null);
                self::printBacktraces($variable->getTrace());
            } elseif (self::isBacktrace($variable)) {
                self::printBacktraces($variable);
            } else {
                self::addToOutput(self::useDumper($variable), null);
            }
            self::addToOutput('</div></div>');
        }
    }

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
        $sql = (string) preg_replace_callback($pattern, static function ($matches) use ($break) {
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

    private static function sqlLink(string $sql): string
    {
        $return = '';
        if (self::$adminerDatabaseName !== null && self::$adminerDatabaseName && self::$adminerUrlLink !== null && self::canBeOutputed()) {
            $query = [
                'username' => self::$adminerUsername,
                'db' => self::$adminerDatabaseName,
                'sql' => trim((string) preg_replace('/[ \t]+/', ' ', $sql)),
            ];
            $return = '<a class="debug-sql-link" target="_blank" href="' . self::$adminerUrlLink . '?' . http_build_query($query) . '">Open using adminer</a>';
        }

        return $return;
    }

    /**
     * @param mixed $variable
     */
    private static function useDumper($variable): string
    {
        $options = self::$dumperOptions;
        if (is_string($variable)) {
            $options[Dumper::TRUNCATE] = null;
        }

        if (self::$forceHTML === true || (PHP_SAPI !== 'cli' && !preg_match('#^Content-Type: (?!text/html)#im', implode("\n", headers_list())))) {
            $string = Dumper::toHtml($variable, $options);
        } elseif (self::detectColors()) {
            $string = Dumper::toTerminal($variable, $options);
        } else {
            $string = Dumper::toText($variable, $options);
        }

        return $string;
    }

    private static function detectColors(): bool
    {
        return Dumper::$terminalColors &&
            (getenv('ConEmuANSI') === 'ON'
                || getenv('ANSICON') !== false
                || getenv('term') === 'xterm-256color'
                || (defined('STDOUT') && function_exists('posix_isatty') && posix_isatty(STDOUT)));
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
     * @return bool
     */
    private static function isBacktrace($variable): bool
    {
        return is_array($variable) &&
            isset($variable[0]) &&
            is_array($variable[0]) &&
            (isset($variable[0]['function']) || isset($variable[0]['file'], $variable[0]['line']));
    }

    private static function printDidYouKnow(): void
    {
        if (!self::$stylesPrinted) {
            $count = count(self::$config['didYouKnow']);
            try {
                $index = random_int(0, $count - 1);
            } catch (Throwable $e) {
                $index = substr((string) time(), -1, 1); //Eh, random enough, shouldn't happen anyway
            }
            self::addToOutput("<div class='debug-didYouKnow'><b>Did you know?</b> " . self::$config['didYouKnow'][$index] . '</b></div>');
        }
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
            if (!self::$stylesPrinted && (!self::$isAjax || self::$forceHTML === true)) {
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
            self::echo(PHP_EOL . $str . self::ajaxOutput() . $str . PHP_EOL, false);
        }
    }

    private static function printStyles(): void
    {
        self::addToOutput(self::getStyles());
        self::$stylesPrinted = true;
    }

    private static function getStyles(): string
    {
        $styles = '';
        $path = __DIR__ . DIRECTORY_SEPARATOR;
        $styles .= '<style>';
        $styles .= FileSystem::read(self::$rootDir . 'vendor/tracy/tracy/src/Tracy/Toggle/toggle.css');
        $styles .= FileSystem::read(self::$rootDir . 'vendor/tracy/tracy/src/Tracy/Dumper/assets/dumper.css');
        $styles .= FileSystem::read($path . 'dumper.css');
        $styles .= '</style>';
        $styles .= '<script>';
        $styles .= FileSystem::read(self::$rootDir . 'vendor/tracy/tracy/src/Tracy/Toggle/toggle.js');
        $styles .= FileSystem::read(self::$rootDir . 'vendor/tracy/tracy/src/Tracy/Dumper/assets/dumper.js');
        $styles .= FileSystem::read($path . 'dumper.js');
        $styles .= '</script>';

        return $styles;
    }

    /**
     * @return string
     */
    private static function ajaxOutput(): string
    {
        if (is_string(self::$output)) {
            $pregReplace = (string) preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", strip_tags(self::$output));

            return str_replace('x//', '', $pregReplace);
        }

        return '';
    }

    private static function getHTML(string $lines): string
    {
        $html = '';
        $html .= '<html lang=""><body>';
        $html .= str_replace(PHP_EOL, ' ', self::getStyles()) . PHP_EOL;
        $html .= $lines;
        $html .= '</body></html>';

        return $html;
    }
}

function dbgr(...$variables)
{
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

    Dbgr::loadDefaultConfig();
    Dbgr::defaultOptions();

    Dbgr::debugProccess($variables, $backtrace);

    return Dbgr::getInstance();
}
