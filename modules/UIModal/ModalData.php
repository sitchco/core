<?php

namespace Sitchco\Modules\UIModal;

readonly class ModalData
{
    /**
     * @param string $id
     * @param ModalType[] $type
     * @param string $content
     * @param string $pre_content
     * @param bool $format_content
     * @param string $label
     */
    public function __construct(
        public string $id,
        public string $content,
        public ModalType $type,
        public string $pre_content = '',
        public bool $format_content = false,
        public string $label = '',
    ) {}

    public function resolvedLabel(): string
    {
        return $this->label ?: str_replace('-', ' ', $this->id);
    }

    public function insertHeading(): bool
    {
        return !empty($this->label) || !str_contains($this->content, '<h');
    }
}
