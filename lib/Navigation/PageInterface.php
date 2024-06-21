<?php
declare(strict_types=1);

namespace Pimcore\Navigation;

interface PageInterface extends \RecursiveIterator
{
    /**
     * Returns page order used in parent container
     *
     * @return int|null  page order or null
     */
    public function getOrder(): ?int;

    /**
     * Checks if the container has the given page
     *
     * @param PageInterface $page page to look for
     * @param bool $recursive [optional] whether to search recursively. Default is false.
     *
     * @return bool whether page is in container
     */
    public function hasPage(PageInterface $page, bool $recursive = false): bool;

    /**
     * Removes the given page from the container
     *
     * @param int|PageInterface $page page to remove, either a page instance or a specific page order
     * @param bool $recursive [optional] whether to remove recursively
     *
     * @return bool whether the removal was successful
     */
    public function removePage(PageInterface|int $page, bool $recursive = false): bool;

    /**
     * Returns a unique code value for the page
     *
     * @return int a unique code value for this page
     */
    public function hashCode(): int;

    /**
     * Returns whether page should be considered active or not
     *
     * @param bool $recursive  [optional] whether page should be considered
     *                          active if any child pages are active. Default is
     *                          false.
     *
     * @return bool             whether page should be considered active
     */
    public function isActive(bool $recursive = false): bool;

    /**
     * Returns a boolean value indicating whether the page is visible
     *
     * @param bool $recursive whether page should be considered invisible if parent is invisible. Default is false.
     *
     * @return bool whether page should be considered visible
     */
    public function isVisible(bool $recursive = false): bool;

    /**
     * Returns the value of the given property
     *
     * If the given property is native (id, class, title, etc), the matching
     * get method will be used. Otherwise, it will return the matching custom
     * property, or null if not found.
     *
     * @param  string $property           property name
     *
     * @return mixed                      the property's value or null
     *
     * @throws \Exception  if property name is invalid
     */
    public function get(string $property): mixed;

    public function toArray(): array;
}
