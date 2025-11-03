<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Mab\SocialLogin\Test\Unit\Helper;

use Mab\SocialLogin\Helper\Data;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Header as HttpHeader;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Mab\Core\Helper\ErrorHandler;
use Psr\Log\LoggerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Unit test for Mab\SocialLogin\Helper\Data
 */
class DataTest extends TestCase
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var ScopeConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $scopeConfigMock;

    /**
     * Setup method
     */
    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);
        
        // Create mocks for dependencies
        $this->scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        
        $context = $this->objectManager->getObject(
            Context::class,
            ['scopeConfig' => $this->scopeConfigMock]
        );
        
        $cacheMock = $this->getMockBuilder(CacheInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
            
        $httpHeaderMock = $this->getMockBuilder(HttpHeader::class)
            ->disableOriginalConstructor()
            ->getMock();
            
        $jsonSerializerMock = $this->getMockBuilder(Json::class)
            ->disableOriginalConstructor()
            ->getMock();
            
        $customerFactoryMock = $this->getMockBuilder(CustomerFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
            
        $customerSessionMock = $this->getMockBuilder(CustomerSession::class)
            ->disableOriginalConstructor()
            ->getMock();
            
        $errorHandlerMock = $this->getMockBuilder(ErrorHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
            
        $loggerMock = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->helper = $this->objectManager->getObject(
            Data::class,
            [
                'context' => $context,
                'cache' => $cacheMock,
                'httpHeader' => $httpHeaderMock,
                'jsonSerializer' => $jsonSerializerMock,
                'customerFactory' => $customerFactoryMock,
                'customerSession' => $customerSessionMock,
                'errorHandler' => $errorHandlerMock,
                'logger' => $loggerMock
            ]
        );
    }

    /**
     * Test isEnabled method
     */
    public function testIsEnabled()
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Data::XML_PATH_ENABLED, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $result = $this->helper->isEnabled();
        $this->assertTrue($result);
    }

    /**
     * Test isGoogleEnabled method when both social login and Google are enabled
     */
    public function testIsGoogleEnabledWhenBothEnabled()
    {
        $this->scopeConfigMock->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnMap([
                [Data::XML_PATH_ENABLED, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null, true],
                [Data::XML_PATH_GOOGLE_ENABLED, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null, true]
            ]);

        $result = $this->helper->isGoogleEnabled();
        $this->assertTrue($result);
    }

    /**
     * Test isGoogleEnabled method when social login is disabled
     */
    public function testIsGoogleEnabledWhenSocialLoginDisabled()
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Data::XML_PATH_ENABLED, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        $result = $this->helper->isGoogleEnabled();
        $this->assertFalse($result);
    }

    /**
     * Test isFacebookEnabled method when both social login and Facebook are enabled
     */
    public function testIsFacebookEnabledWhenBothEnabled()
    {
        $this->scopeConfigMock->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnMap([
                [Data::XML_PATH_ENABLED, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null, true],
                [Data::XML_PATH_FACEBOOK_ENABLED, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null, true]
            ]);

        $result = $this->helper->isFacebookEnabled();
        $this->assertTrue($result);
    }

    /**
     * Test getGoogleClientId method
     */
    public function testGetGoogleClientId()
    {
        $clientId = 'test-client-id';
        
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Data::XML_PATH_GOOGLE_CLIENT_ID, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn($clientId);

        $result = $this->helper->getGoogleClientId();
        $this->assertEquals($clientId, $result);
    }

    /**
     * Test getButtonStyle method
     */
    public function testGetButtonStyle()
    {
        $buttonStyle = 'default';
        
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Data::XML_PATH_BUTTON_STYLE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn($buttonStyle);

        $result = $this->helper->getButtonStyle();
        $this->assertEquals($buttonStyle, $result);
    }

    /**
     * Test getButtonSize method
     */
    public function testGetButtonSize()
    {
        $buttonSize = 'medium';
        
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Data::XML_PATH_BUTTON_SIZE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null)
            ->willReturn($buttonSize);

        $result = $this->helper->getButtonSize();
        $this->assertEquals($buttonSize, $result);
    }

    /**
     * Test isRememberMeEnabled method
     */
    public function testIsRememberMeEnabled()
    {
        $this->scopeConfigMock->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnMap([
                [Data::XML_PATH_EXTENDED_LIFETIME_ENABLED, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null, true],
                [Data::XML_PATH_REMEMBER_ME_ENABLED, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, null, true]
            ]);

        $result = $this->helper->isRememberMeEnabled();
        $this->assertTrue($result);
    }
}