<?php

/*
 * This file is part of the Vatsimphp package
 *
 * Copyright 2013 - Jelle Vink <jelle.vink@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace Vatsimphp;

use Vatsimphp\Filter\Iterator;
use Vatsimphp\VatsimData;
use Vatsimphp\Result\ResultContainer;
use Vatsimphp\Sync\AbstractSync;

class VatsimDataTest extends \PHPUnit_Framework_TestCase
{
    /**
     *
     * Test config setter/getter
     * @covers Vatsimphp\VatsimData::getConfig
     * @covers Vatsimphp\VatsimData::setConfig
     */
    public function testConfig()
    {
        $data = $this->getDataMock();

        // available config keys
        $this->assertArrayHasKey('cacheDir', $data->getConfig());
        $this->assertArrayHasKey('cacheOnly', $data->getConfig());
        $this->assertArrayHasKey('statusUrl', $data->getConfig());
        $this->assertArrayHasKey('statusRefresh', $data->getConfig());
        $this->assertArrayHasKey('dataRefresh', $data->getConfig());
        $this->assertArrayHasKey('dataExpire', $data->getConfig());
        $this->assertArrayHasKey('forceDataRefresh', $data->getConfig());
        $this->assertArrayHasKey('metarRefresh', $data->getConfig());
        $this->assertArrayHasKey('forceMetarRefresh', $data->getConfig());

        // not accepting unknown keys
        $data->setConfig('foo', 'bar');
        $this->assertArrayNotHasKey('foo', $data->getConfig());
        $this->assertNull($data->getConfig('foo'));

        // update keys
        $data->setConfig('cacheDir', '/tmp/bogus/dir');
        $this->assertArrayHasKey('cacheDir', $data->getConfig());
        $this->assertSame('/tmp/bogus/dir', $data->getConfig('cacheDir'));
    }

    /**
     *
     * @covers Vatsimphp\VatsimData::search
     */
    public function testSearch()
    {
        $container = $this->getContainerMock(array('search'));
        $container->expects($this->once())
            ->method('search')
            ->with($this->equalTo('general'), $this->equalTo('query'));
        $data = $this->attachContainer($this->getDataMock(), $container);
        $data->search('general', 'query');
    }

    /**
     *
     * @covers Vatsimphp\VatsimData::searchCallsign
     */
    public function testSearchCallsign()
    {
        $data = $this->getDataMock(array('search'));
        $container = $this->getContainerMock();
        $data = $this->attachContainer($data, $container);
        $data->expects($this->once())
            ->method('search')
            ->with($this->equalTo('clients'), $this->equalTo(array('callsign' => 'SWA3437')));
        $data->searchCallsign('SWA3437');
    }

    /**
     *
     * @covers Vatsimphp\VatsimData::getMetar
     */
    public function testGetMetar()
    {
        $data = $this->getDataMock(array('loadMetar'));
        $data->expects($this->once())
            ->method('loadMetar')
            ->will($this->returnValue(true));

        $container = $this->getContainerMock();
        $container->append('metar', array('KSFO stuff'));
        $data = $this->attachContainer($data, $container);

        $this->assertEquals('KSFO stuff', $data->getMetar('KSFO'));
    }

    /**
     *
     * @covers Vatsimphp\VatsimData::getObjectTypes
     */
    public function testGetObjectTypes()
    {
        $container = $this->getContainerMock(array('getList'));
        $container->expects($this->once())
            ->method('getList');
        $data = $this->attachContainer($this->getDataMock(), $container);
        $data->getObjectTypes();
    }

    /**
     *
     * @covers Vatsimphp\VatsimData::getIterator
     * @covers Vatsimphp\VatsimData::__get
     */
    public function testGetIterator()
    {
        $container = $this->getContainerMock();
        $container->expects($this->any())
            ->method('get')
            ->with($this->equalTo('general'));
        $data = $this->attachContainer($this->getDataMock(), $container);
        $data->getIterator('general');
        $data->general;
    }

    /**
     *
     * @covers Vatsimphp\VatsimData::getArray
     */
    public function testGetArray()
    {
        // iterator mock for getIterator to be able to call toArray
        $iterator = $this->getMockBuilder('Vatsimphp\Filter\AbstractFilter')
            ->setConstructorArgs(array(array()))
            ->setMethods(array())
            ->getMockForAbstractClass();

        $data = $this->getDataMock(array('getIterator'));
        $container = $this->getContainerMock();
        $data = $this->attachContainer($data, $container);
        $data->expects($this->once())
            ->method('getIterator')
            ->with($this->equalTo('general'))
            ->will($this->returnValue($iterator));
        $data->getArray('general');
    }

    /**
     *
     * @covers Vatsimphp\VatsimData::loadData
     */
    public function testLoadData()
    {
        $statusSync = $this->getSyncMock('status');

        $dataSync   = $this->getSyncMock('data');
        $dataSync->expects($this->once())
            ->method('setDefaults');
        $dataSync->expects($this->once())
            ->method('registerUrlFromStatus');
        $dataSync->expects($this->once())
            ->method('loadData');

        $data = $this->getDataMock(array('prepareSync', 'getDataSync'));
        $data->expects($this->once())
            ->method('prepareSync')
            ->will($this->returnValue($statusSync));
        $data->expects($this->once())
            ->method('getDataSync')
            ->will($this->returnValue($dataSync));

        $this->assertTrue($data->loadData());
    }

    /**
     * Exception stack test
     * @covers Vatsimphp\VatsimData::loadData
     * @covers Vatsimphp\VatsimData::getExceptionStack
     */
    public function testLoadDataException()
    {
        $statusSync = $this->getSyncMock('status');

        $dataSync   = $this->getSyncMock('data');
        $dataSync->expects($this->once())
            ->method('loadData')
            ->will($this->throwException(new \Exception));

        $data = $this->getDataMock(array('prepareSync', 'getDataSync'));
        $data->expects($this->once())
            ->method('prepareSync')
            ->will($this->returnValue($statusSync));
        $data->expects($this->once())
            ->method('getDataSync')
            ->will($this->returnValue($dataSync));

        $this->assertFalse($data->loadData());
        $stack = $data->getExceptionStack();
        $this->assertCount(1, $stack);
        $this->assertArrayHasKey(0, $stack);
        $this->assertInstanceOf('\Exception', $stack[0]);
    }

    /**
     *
     * @covers Vatsimphp\VatsimData::loadMetar
     */
    public function testLoadMetar()
    {
        $metarSync = $this->getSyncMock('metar');
        $metarSync->expects($this->once())
            ->method('setAirport')
            ->with($this->equalTo('KSFO'));
        $metarSync->expects($this->once())
            ->method('loadData');

        $data = $this->getDataMock(array('prepareMetarSync'));
        $data->expects($this->once())
            ->method('prepareMetarSync')
            ->will($this->returnValue($metarSync));

        $this->assertTrue($data->loadMetar('KSFO'));
    }

    /**
     * Exception stack test
     * @covers Vatsimphp\VatsimData::loadMetar
     * @covers Vatsimphp\VatsimData::getExceptionStack
     */
    public function testLoadMetarException()
    {
        $metarSync = $this->getSyncMock('metar');
        $metarSync->expects($this->once())
            ->method('loadData')
            ->will($this->throwException(new \Exception));

        $data = $this->getDataMock(array('prepareMetarSync'));
        $data->expects($this->once())
            ->method('prepareMetarSync')
            ->will($this->returnValue($metarSync));

        $this->assertFalse($data->loadMetar('KSFO'));
        $stack = $data->getExceptionStack();
        $this->assertCount(1, $stack);
        $this->assertArrayHasKey(0, $stack);
        $this->assertInstanceOf('\Exception', $stack[0]);
    }

    /**
     *
     * @covers Vatsimphp\VatsimData::prepareSync
     */
    public function testPrepareSync()
    {
        $statusSync = $this->getSyncMock('status');
        $statusSync->expects($this->once())
            ->method('setDefaults');
        $statusSync->expects($this->once())
            ->method('registerUrl')
            ->with($this->equalTo('custom_url'), $this->equalTo(true));

        $data = $this->getDataMock(array('getStatusSync'));
        $data->setConfig('statusUrl', 'custom_url');
        $data->expects($this->once())
            ->method('getStatusSync')
            ->will($this->returnValue($statusSync));

        $prepare = new \ReflectionMethod($data, 'prepareSync');
        $prepare->setAccessible(true);
        $this->assertInstanceOf('Vatsimphp\Sync\StatusSync', $prepare->invoke($data));

    }

    /**
     *
     * @covers Vatsimphp\VatsimData::prepareMetarSync
     */
    public function testPrepareMetarSync()
    {
        $statusSync = $this->getSyncMock('status');
        $metarSync = $this->getSyncMock('metar');
        $metarSync->expects($this->once())
            ->method('setDefaults');
        $metarSync->expects($this->once())
            ->method('registerUrlFromStatus')
            ->with($this->isInstanceOf('Vatsimphp\Sync\StatusSync'), $this->equalTo('metarUrls'));

        $data = $this->getDataMock(array('prepareSync', 'getMetarSync'));
        $data->expects($this->once())
            ->method('prepareSync')
            ->will($this->returnValue($statusSync));
        $data->expects($this->once())
            ->method('getMetarSync')
            ->will($this->returnValue($metarSync));

        $prepare = new \ReflectionMethod($data, 'prepareMetarSync');
        $prepare->setAccessible(true);
        $this->assertInstanceOf('Vatsimphp\Sync\MetarSync', $prepare->invoke($data));

    }

    /**
     *
     * Test proper return of cached objects to avoid multiple invocations
     *
     * @dataProvider providerTestCachedObjects
     * @covers Vatsimphp\VatsimData::prepareSync
     * @covers Vatsimphp\VatsimData::prepareMetarSync
     */
    public function testCachedObjects($name, $cacheProp, $notCallFunction, $class, $testMethod)
    {
        $object = $this->getSyncMock($name);

        $data = $this->getDataMock(array($notCallFunction));
        $data->expects($this->never())
            ->method($notCallFunction);

        $cache = new \ReflectionProperty($data, $cacheProp);
        $cache->setAccessible(true);
        $cache->setValue($data, $object);

        $test = new \ReflectionMethod($data, $testMethod);
        $test->setAccessible(true);
        $this->assertInstanceOf($class, $test->invoke($data));
    }

    public function providerTestCachedObjects()
    {
        return array(
            array('status', 'statusSync', 'getStatusSync', 'Vatsimphp\Sync\StatusSync', 'prepareSync'),
            array('metar', 'metarSync', 'getMetarSync', 'Vatsimphp\Sync\MetarSync', 'prepareMetarSync'),
        );
    }

    /**
     * @dataProvider providerTestSyncGetters
     * @covers Vatsimphp\VatsimData::getStatusSync
     * @covers Vatsimphp\VatsimData::getDataSync
     * @covers Vatsimphp\VatsimData::getMetarSync
     */
    public function testSyncGetters($name)
    {
        $data = $this->getDataMock();
        $getMethod = "get{$name}Sync";
        $classname = "Vatsimphp\\Sync\\{$name}Sync";
        $reflection = new \ReflectionMethod($data, $getMethod);
        $reflection->setAccessible(true);
        $this->assertInstanceOf($classname, $reflection->invoke($data));
    }

    public function providerTestSyncGetters()
    {
        return array(
            array('Status'),
            array('Data'),
            array('Metar'),
        );
    }

    /**
     *
     * @param mixed $setMethods To be passed into setMethods mock builder
     * @return Vatsimphp\VatsimData
     */
    protected function getDataMock($setMethods = null)
    {
        return $this->getMockBuilder('Vatsimphp\VatsimData')
            ->disableOriginalConstructor()
            ->setMethods($setMethods)
            ->getMock();
    }

    /**
     *
     * @return Vatsimphp\Result\ResultContainer
     */
    protected function getContainerMock($setMethods = null)
    {
        return $this->getMockBuilder('Vatsimphp\Result\ResultContainer')
            ->setMethods($setMethods)
            ->getMock();
    }

    /**
     *
     * Attach result container to data object
     * @param Vatsimphp\VatsimData $data
     * @param Vatsimphp\Result\ResultContainer $container
     * @return Vatsimphp\VatsimData
     */
    protected function attachContainer(VatsimData $data, ResultContainer $container)
    {
        $result = new \ReflectionProperty($data, 'results');
        $result->setAccessible(true);
        $result->setValue($data, $container);
        return $data;
    }

    /**
     *
     * Return Sync mock
     * @param string $name
     * @return Vatsimphp\Sync\AbstractSync
     */
    protected function getSyncMock($name)
    {
        $class = 'Vatsimphp\\Sync\\' . ucfirst($name) . 'Sync';
        $sync = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->setMethods(array())
            ->getMock();
        return $this->attachMockedLogger($sync);
    }

    /**
     *
     * Attach mocked silent logger
     * @param string $class
     * @return Vatsimphp\Sync\AbstractSync
     */
    protected function attachMockedLogger($class)
    {
        $silentLogger = $this->getMockBuilder('Vatsimphp\Log\Logger')
            ->getMock();
        $logger = new \ReflectionProperty($class, 'log');
        $logger->setAccessible(true);
        $logger->setValue($class, $silentLogger);
        return $class;
    }
}
