<?php

namespace Sitchco\FlashMessage;

/**
 * Class FlashMessage
 * @package Sitchco\FlashMessage
 */
class FlashMessage
{
    const FLASH_PREFIX = 'sitchco_admin_notification';

    protected string $optionKey;

    /**
     * FlashMessage constructor.
     *
     * @param string $prefix The prefix used to generate the unique option key.
     */
    public function __construct(string $prefix)
    {
        $this->optionKey = sprintf('%s_%d', $prefix, get_current_user_id());
    }

    /**
     * Stores a value as a flash message in the WordPress options table.
     *
     * @param mixed $value The value to store.
     * @return bool True if the option was successfully updated, false otherwise.
     */
    public function setValue(mixed $value): bool
    {
        return update_option($this->optionKey, $value, false);
    }

    /**
     * Retrieves the flash message value and removes it from storage.
     *
     * @return mixed The stored value, or null if not found.
     */
    public function getValue(): mixed
    {
        $value = get_option($this->optionKey);
        delete_option($this->optionKey);
        return $value;
    }

    /**
     * Creates a new flash message instance and stores the provided value.
     *
     * @param mixed $value The value to store.
     * @param string $prefix The prefix used to generate the unique option key.
     * @return static The FlashMessage instance.
     */
    public static function create(mixed $value, string $prefix = self::FLASH_PREFIX): static
    {
        $flash = new static($prefix);
        $flash->setValue($value);
        return $flash;
    }

    /**
     * Retrieves and deletes the stored flash message.
     *
     * @param string $prefix The prefix used to generate the unique option key.
     * @return mixed The stored value, or null if not found.
     */
    public static function get(string $prefix = self::FLASH_PREFIX): mixed
    {
        $flash = new static($prefix);
        return $flash->getValue();
    }
}
