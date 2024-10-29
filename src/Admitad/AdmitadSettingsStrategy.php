<?php

declare(strict_types=1);

namespace Admitad;

class AdmitadSettingsStrategy
{
    protected ?AdmitadContainer $container = null;

    public function setContainer(AdmitadContainer $container)
    {
        $this->container = $container;
    }

    public function save(string $name, string $key, mixed $value): self
    {
        return $this;
    }

    public function get(string $optionName, string $key = null): mixed
    {
        $optionName = 'admitadtracking_' . $optionName;

        $result = get_option($optionName);

        if (null === $key) {
            return $result;
        }

        if (!isset($result[$key])) {
            return null;
        }

        return $result[$key];
    }

    public function getTariffMap(string $optionName = 'actions'): array
    {
        return $this->get($optionName, 'actions') ?: [];
    }

    public function getCookieName(): string
    {
        return '_aid';
    }

    public function getParamName(): string
    {
        return 'admitad_uid';
    }
}
