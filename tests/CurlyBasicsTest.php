<?php

namespace ivuorinen\Curly\Tests;

use PHPUnit\Framework\TestCase;

class CurlyBasicsTest extends TestCase
{
    /**
     * Just check if the YourClass has no syntax error
     *
     * This is just a simple check to make sure your library has no
     * syntax error. This helps you troubleshoot any typo before you
     * even use this library in a real project.
     *
     * @throws \ivuorinen\Curly\Exceptions\HTTPException
     */
    public function testIsThereAnySyntaxError(): void
    {
        $var = new \ivuorinen\Curly\Curly();
        $this->assertIsObject($var);
        unset($var);
    }

    /**
     * Just check if the Curly has no syntax errors
     *
     * This is just a simple check to make sure your library has no
     * syntax error. This helps you troubleshoot any typo before you
     * even use this library in a real project.
     *
     * @throws \ivuorinen\Curly\Exceptions\HTTPException
     */
    public function testParseData(): void
    {
        $var = new \ivuorinen\Curly\Curly();

        $expected = "foo=bar&baz=buzz";

        // String
        $this->assertEquals($expected, $var->parseData($expected));

        // Array
        $this->assertEquals(
            $expected,
            $var->parseData(['foo' => 'bar', 'baz' => 'buzz'])
        );

        // Object
        $actual      = new \stdClass();
        $actual->foo = "bar";
        $actual->baz = "buzz";

        $this->assertEquals(
            $expected,
            $var->parseData($actual)
        );

        unset($var);
    }
}
