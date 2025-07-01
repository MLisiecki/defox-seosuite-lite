<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */

declare(strict_types=1);

namespace Defox\SEOSuite\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Defox\SEOSuite\Logger\Logger;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Main helper class for the module.
 */
class Data extends AbstractHelper
{
    /**
     * Configuration paths.
     */
    public const XML_PATH_MODULE_ENABLED = 'defox_seosuite/general/enabled';
    public const XML_PATH_DEBUG_MODE = 'defox_seosuite/general/debug_mode';

    /**
     * @var Logger
     */
    protected Logger $logger;

    /**
     * @var SerializerInterface
     */
    protected SerializerInterface $serializer;

    /**
     * @param Context $context
     * @param Logger $logger
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Context $context,
        Logger $logger,
        SerializerInterface $serializer
    ) {
        $this->logger = $logger;
        $this->serializer = $serializer;
        parent::__construct($context);
    }

    /**
     * Check if module is enabled.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isModuleEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_MODULE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if debug mode is enabled.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isDebugMode(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEBUG_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get configuration value.
     *
     * @param string $path
     * @param int|null $storeId
     * @return mixed
     */
    public function getConfig(string $path, ?int $storeId = null): mixed
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get flag configuration value.
     *
     * @param string $path
     * @param int|null $storeId
     * @return bool
     */
    public function getConfigFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Log info message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * Log error message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * Log debug message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function logDebug(string $message, array $context = []): void
    {
        if ($this->isDebugMode()) {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * Serialize data.
     *
     * @param mixed $data
     * @return string
     */
    public function serialize(mixed $data): string
    {
        return $this->serializer->serialize($data);
    }

    /**
     * Unserialize data.
     *
     * @param string $string
     * @return mixed
     */
    public function unserialize(string $string): mixed
    {
        if (empty($string)) {
            return [];
        }
        
        try {
            return $this->serializer->unserialize($string);
        } catch (\Exception $e) {
            $this->logError('Failed to unserialize data: ' . $e->getMessage());
            return [];
        }
    }
}
