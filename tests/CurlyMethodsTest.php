<?php

namespace ivuorinen\Curly\Tests;

use ivuorinen\Curly\Curly;
use PHPUnit\Framework\TestCase;

class CurlyMethodsTest extends TestCase
{
    protected $curly = false;

    public function testPort()
    {
        $this->assertEquals(80, $this->curly->getPort());
        $this->curly->setPort(1234);
        $this->assertEquals(1234, $this->curly->getPort());
    }

    public function testTimeout()
    {
        $this->assertEquals(30, $this->curly->getTimeout());
        $this->curly->setTimeout(10);
        $this->assertEquals(10, $this->curly->getTimeout());
    }

    public function testSkipSSLVerify()
    {
        $this->assertTrue(method_exists(
            $this->curly,
            'getSkipSSLVerify'
        ));
        $this->assertTrue(method_exists(
            $this->curly,
            'setSkipSSLVerify'
        ));

        $this->assertFalse($this->curly->getSkipSSLVerify());
        $this->curly->setSkipSSLVerify(true);
        $this->assertTrue($this->curly->getSkipSSLVerify());
        $this->curly->setSkipSSLVerify(false);
        $this->assertFalse($this->curly->getSkipSSLVerify());
    }

    public function testHttpMethod()
    {
        $this->assertTrue(method_exists($this->curly, 'getMethod'));
        $this->assertTrue(method_exists($this->curly, 'setMethod'));

        $this->assertEquals('GET', $this->curly->getMethod());

        $this->curly->setMethod('POST');
        $this->assertEquals(
            'POST',
            $this->curly->getMethod()
        );
    }

    public function testVerbose()
    {
        $this->assertTrue(method_exists($this->curly, 'getVerbose'));
        $this->assertTrue(method_exists($this->curly, 'setVerbose'));

        $this->assertFalse($this->curly->getVerbose());
        $this->curly->setVerbose(true);
        $this->assertTrue($this->curly->getVerbose());
    }

    public function testProxy()
    {
        $this->assertTrue(method_exists($this->curly, 'getProxy'));
        $this->assertTrue(method_exists($this->curly, 'setProxy'));

        $this->assertNull($this->curly->getProxy());

        $proxy = 'http://example.com';
        $this->curly->setProxy($proxy);
        $this->assertEquals($proxy, $this->curly->getProxy());
    }

    public function testUserAgent()
    {
        $this->assertTrue(method_exists($this->curly, 'getUserAgent'));
        $this->assertTrue(method_exists($this->curly, 'setUserAgent'));

        $this->assertEquals(
            'ivuorinen-curly',
            $this->curly->getUserAgent()
        );

        $this->curly->setUserAgent('testing');
        $this->assertEquals(
            'testing',
            $this->curly->getUserAgent()
        );
    }

    public function testHeaders()
    {
        $this->assertTrue(method_exists(
            $this->curly, 'getHeaders'
        ));
        $this->assertTrue(method_exists(
            $this->curly, 'setHeader'
        ));
        $this->assertTrue(method_exists(
            $this->curly, 'getHeader'
        ));
        $this->assertTrue(method_exists(
            $this->curly, 'unsetHeader'
        ));

        $headers = $this->curly->getHeaders();

        $defaultHeaders = [
            'Accept',
            'Accept-Language',
            'Accept-Encoding',
            'Accept-Charset'
        ];
        foreach ($defaultHeaders as $defaultHeader) {
            $this->assertArrayHasKey(
                $defaultHeader, $headers, $defaultHeader
            );

            $this->curly->setHeader($defaultHeader, 'testing');

            $this->assertEquals(
                'testing',
                $this->curly->getHeader($defaultHeader)
            );

            $this->curly->unsetHeader($defaultHeader);
            $this->assertFalse($this->curly->getHeader($defaultHeader));
        }
    }

    public function testContentType()
    {
        $this->assertTrue(method_exists(
            $this->curly, 'getContentType'
        ));
        $this->assertTrue(method_exists(
            $this->curly, 'setContentType'
        ));

        $default = 'application/x-www-form-urlencoded';
        $this->assertEquals($default, $this->curly->getContentType());

        $this->curly->setContentType('testing');
        $this->assertEquals(
            'testing',
            $this->curly->getContentType()
        );
    }

    public function testAuth()
    {
        $this->curly->setAuth(
            'BASIC',
            'user',
            'pass'
        );

        $this->assertEquals(
            'BASIC',
            $this->curly->getAuthenticationMethod()
        );
        $this->assertEquals('user', $this->curly->getUser());
        $this->assertEquals('pass', $this->curly->getPass());
    }

    public function testHttpVersion()
    {
        $this->assertEquals(
            '1.0',
            $this->curly->getHttpVersion()
        );

        $this->curly->setHttpVersion('1.1');
        $this->assertEquals(
            '1.1',
            $this->curly->getHttpVersion()
        );

        $this->curly->setHttpVersion('1.2');
        $this->assertEquals(
            'NONE',
            $this->curly->getHttpVersion()
        );
    }

    public function testHost()
    {
        $test = 'http://user:password@example.com/xyz';
        $this->curly->setHost($test);
        $this->assertEquals($test, $this->curly->getHost());
    }

    /**
     * @throws \ivuorinen\Curly\Exceptions\HTTPException
     */
    protected function setUp() : void
    {
        parent::setUp();
        $this->curly = new Curly;
    }
}
