<?php

namespace DNADesign\Populate\Tests;

use DNADesign\Populate\Populate;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\SapphireTest;
use ReflectionClass;

class PopulateTest extends SapphireTest
{
    private $environment = null;

    public function testAllowOnDev(): void
    {
        /** @var Kernel $kernel */
        $kernel = Injector::inst()->get(Kernel::class);
        $kernel->setEnvironment('dev');

        // Use Reflection so that we can access private method
        $reflection = new ReflectionClass(Populate::class);
        $method = $reflection->getMethod('canBuildOnEnvironment');
        $method->setAccessible(true);

        // Invoke with null object, as this method is static
        $this->assertTrue($method->invoke(null));
    }

    public function testAllowOnTest(): void
    {
        /** @var Kernel $kernel */
        $kernel = Injector::inst()->get(Kernel::class);
        $kernel->setEnvironment('test');

        // Use Reflection so that we can access private method
        $reflection = new ReflectionClass(Populate::class);
        $method = $reflection->getMethod('canBuildOnEnvironment');
        $method->setAccessible(true);

        // Invoke with null object, as this method is static
        $this->assertTrue($method->invoke(null));
    }

    public function testDenyBuildOnLive(): void
    {
        /** @var Kernel $kernel */
        $kernel = Injector::inst()->get(Kernel::class);
        $kernel->setEnvironment('live');

        // Use Reflection so that we can access private method
        $reflection = new ReflectionClass(Populate::class);
        $method = $reflection->getMethod('canBuildOnEnvironment');
        $method->setAccessible(true);

        // Invoke with null object, as this method is static
        $this->assertFalse($method->invoke(null));
    }

    public function testAllowBuildOnLive(): void
    {
        Populate::config()->set('allow_build_on_live', true);

        /** @var Kernel $kernel */
        $kernel = Injector::inst()->get(Kernel::class);
        $kernel->setEnvironment('live');

        // Use Reflection so that we can access private method
        $reflection = new ReflectionClass(Populate::class);
        $method = $reflection->getMethod('canBuildOnEnvironment');
        $method->setAccessible(true);

        // Invoke with null object, as this method is static
        $this->assertTrue($method->invoke(null));
    }

    protected function setUp()
    {
        parent::setUp();

        // Make sure that we store the current environment before we kick off our tests
        /** @var Kernel $kernel */
        $kernel = Injector::inst()->get(Kernel::class);

        $this->environment = $kernel->getEnvironment();
    }

    protected function tearDown()
    {
        // Make sure we reset the environment mode at the end of the test
        /** @var Kernel $kernel */
        $kernel = Injector::inst()->get(Kernel::class);
        $kernel->setEnvironment($this->environment);

        parent::tearDown();
    }
}
