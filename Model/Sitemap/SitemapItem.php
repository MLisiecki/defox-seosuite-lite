<?php
/**
 * @package     Defox_SEOSuite
 * @author      Marcin Lisiecki 
 * @copyright   Copyright (c) 2025 deFox
 * @license     MIT
 */
declare(strict_types=1);

namespace Defox\SEOSuite\Model\Sitemap;

/**
 * Basic implementation of sitemap item
 */
class SitemapItem implements SitemapItemInterface
{
    /**
     * @var string
     */
    private string $url;

    /**
     * @var string|null
     */
    private ?string $lastmod;

    /**
     * @var string|null
     */
    private ?string $changefreq;

    /**
     * @var float|null
     */
    private ?float $priority;

    /**
     * @var array
     */
    private array $alternates;

    /**
     * @var array
     */
    private array $images;

    /**
     * @var bool
     */
    private bool $included;

    /**
     * Constructor
     *
     * @param string $url
     * @param string|null $lastmod
     * @param string|null $changefreq
     * @param float|null $priority
     * @param array $alternates
     * @param array $images
     * @param bool $included
     */
    public function __construct(
        string $url,
        ?string $lastmod = null,
        ?string $changefreq = null,
        ?float $priority = null,
        array $alternates = [],
        array $images = [],
        bool $included = true
    ) {
        $this->url = $url;
        $this->lastmod = $lastmod;
        $this->changefreq = $changefreq;
        $this->priority = $priority;
        $this->alternates = $alternates;
        $this->images = $images;
        $this->included = $included;
    }

    /**
     * @inheritDoc
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @inheritDoc
     */
    public function getLastmod(): ?string
    {
        return $this->lastmod;
    }

    /**
     * @inheritDoc
     */
    public function getChangefreq(): ?string
    {
        return $this->changefreq;
    }

    /**
     * @inheritDoc
     */
    public function getPriority(): ?float
    {
        return $this->priority;
    }

    /**
     * @inheritDoc
     */
    public function getAlternates(): array
    {
        return $this->alternates;
    }

    /**
     * @inheritDoc
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * @inheritDoc
     */
    public function isIncluded(): bool
    {
        return $this->included;
    }

    /**
     * Set URL
     *
     * @param string $url
     * @return self
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Set last modification date
     *
     * @param string|null $lastmod
     * @return self
     */
    public function setLastmod(?string $lastmod): self
    {
        $this->lastmod = $lastmod;
        return $this;
    }

    /**
     * Set change frequency
     *
     * @param string|null $changefreq
     * @return self
     */
    public function setChangefreq(?string $changefreq): self
    {
        $this->changefreq = $changefreq;
        return $this;
    }

    /**
     * Set priority
     *
     * @param float|null $priority
     * @return self
     */
    public function setPriority(?float $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * Set alternates
     *
     * @param array $alternates
     * @return self
     */
    public function setAlternates(array $alternates): self
    {
        $this->alternates = $alternates;
        return $this;
    }

    /**
     * Add alternate
     *
     * @param string $lang
     * @param string $url
     * @return self
     */
    public function addAlternate(string $lang, string $url): self
    {
        $this->alternates[] = ['lang' => $lang, 'url' => $url];
        return $this;
    }

    /**
     * Set images
     *
     * @param array $images
     * @return self
     */
    public function setImages(array $images): self
    {
        $this->images = $images;
        return $this;
    }

    /**
     * Add image
     *
     * @param string $url
     * @param string|null $title
     * @param string|null $caption
     * @return self
     */
    public function addImage(string $url, ?string $title = null, ?string $caption = null): self
    {
        $this->images[] = [
            'url' => $url,
            'title' => $title,
            'caption' => $caption
        ];
        return $this;
    }

    /**
     * Set included status
     *
     * @param bool $included
     * @return self
     */
    public function setIncluded(bool $included): self
    {
        $this->included = $included;
        return $this;
    }
}
