<?php

declare(strict_types=1);

namespace Admitad;

class AdmitadContainer
{
    protected array $params = [];

    public function set($key, $value): self
    {
        $this->params[$key] = $value;

        return $this;
    }

    public function get($key)
    {
        if (!isset($this->params[$key])) {
            return null;
        }

        return $this->params[$key];
    }

    public function getApi(): AdmitadApi
    {
        if (!$this->get('api')) {
            $this->set('api', new AdmitadApi($this));
        }

        return $this->get('api');
    }

    public function getAdmitadManager(): AdmitadManager
    {
        if (!$this->get('manager')) {
            $this->set('manager', new AdmitadManager($this));
        }

        return $this->get('manager');
    }

    public function getParameters(): AdmitadParameterStrategy
    {
        if (!$this->get('parameters')) {
            $this->set('parameters', new AdmitadParameterStrategy($this));
        }

        return $this->get('parameters');
    }

    public function getSettings(): AdmitadSettingsStrategy
    {
        if (!$this->get('settings')) {
            $this->set('settings', new AdmitadSettingsStrategy());
        }

        return $this->get('settings');
    }
}
