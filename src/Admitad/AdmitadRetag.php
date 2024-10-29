<?php

declare(strict_types=1);

namespace Admitad;

class AdmitadRetag
{
    public function __construct(protected AdmitadParameterStrategy $parameters) {}

    /**
     * Возвращает скрипт ретага успешного оформления заказа по коду.
     *
     * @param mixed $code
     */
    public function getCheckoutRetag($code): string
    {
        return $this->getScript($code, 4, $this->parameters->getCheckoutRetagParams());
    }

    /**
     * Возвращает скрипт ретага корзины по коду.
     *
     * @param mixed $code
     */
    public function getCartRetag($code): string
    {
        return $this->getScript($code, 3, $this->parameters->getCartRetagParams());
    }

    /**
     * Возвращает скрипт ретага страницы товара по коду.
     *
     * @param mixed $code
     */
    public function getProductRetag($code): string
    {
        return $this->getScript($code, 2, $this->parameters->getProductRetagParams());
    }

    /**
     * Возвращает скрипт ретага страницы категории по коду.
     *
     * @param mixed $code
     */
    public function getCategoryRetag($code): string
    {
        return $this->getScript($code, 1, $this->parameters->getCategoryRetagParams());
    }

    /**
     * Возвращает скрипт ретага главной страницы по коду.
     *
     * @param mixed $code
     */
    public function getMainRetag($code): string
    {
        return $this->getScript($code, 0);
    }

    protected function getScript(string $code, int $level, array $params = []): string
    {
        $vars = '';

        foreach ($params as $key => $value) {
            $vars .= 'window.' . $key . ' = ' . json_encode($value, JSON_UNESCAPED_UNICODE) . ';' . PHP_EOL;
        }

        return '
            <script type="text/javascript">
                ' . $vars . '
                
                window._retag = window._retag || [];
                window._retag.push({code: "' . $code . '", level: ' . $level . '});
                (function () {
                    var id = "admitad-retag";
                    if (document.getElementById(id)) {return;}
                    var s = document.createElement("script");
                    s.async = true; s.id = id;
                    var r = (new Date).getDate();
                    s.src = (document.location.protocol == "https:" ? "https:" : "http:") + "//cdn.lenmit.com/static/js/retag.js?r="+r;
                    var a = document.getElementsByTagName("script")[0]
                    a.parentNode.insertBefore(s, a);
                })()
            </script>
        ';
    }
}
