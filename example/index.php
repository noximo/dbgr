<?php
declare(strict_types=1);

use Nette\Utils\Random;
use noximo\Dbgr;

include dirname(__DIR__) . '/vendor/autoload.php';



class Test
{
    public $name;
    private $test;

    /**
     * Test constructor.
     *
     * @param $name
     * @param $test
     */
    public function __construct($name, $test)
    {
        $this->name = $name;
        $this->test = $test;
    }

}

class TestDebugInfo
{
    public $name;
    private $test;

    /**
     * Test constructor.
     *
     * @param $name
     * @param $test
     */
    public function __construct($name, $test)
    {
        $this->name = $name;
        $this->test = $test;
    }

    public function __debugInfo()
    {
        return [
            $this->name,
            'TEST',
        ];
    }

}

try {
    $debugger = [
        [[[[[[Random::generate(50)]]]]]],
        Random::generate(50),
        Random::generate(50),
        Random::generate(50),
        Random::generate(50),
    ];

    $sql = "SELECT * FROM dual WHERE 1=1;";

    $object = new stdClass();
    $object->name = Random::generate(50);
    $object->test = Random::generate(50);

    $class = new Test(Random::generate(50), Random::generate(50));
    $classDebugInfo = new TestDebugInfo(Random::generate(50), Random::generate(50));
    Dbgr::setConfig([
        'adminerDatabaseName' => 'test',
        'adminerUsername' => 'develop',

    ]);
    Dbgr::dump($debugger, $sql, $object, $class, $classDebugInfo);
    Dbgr::dump($debugger, $sql, $object, $class, $classDebugInfo);
    Dbgr::dump($debugger, $sql, $object, $class, $classDebugInfo);
    Dbgr::dump($debugger, $sql, $object, $class, $classDebugInfo);
    Dbgr::dump($debugger, $sql, $object, $class, $classDebugInfo);
} catch (Throwable $e) {
    echo $e->getMessage();
}
