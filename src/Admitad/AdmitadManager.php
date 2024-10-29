<?php

declare(strict_types=1);

namespace Admitad;

use stdClass;

class AdmitadManager
{
    protected $advertiserInfo;

    public function __construct(protected AdmitadContainer $container) {}

    /**
     * В случае успешного запроса возвращает информацию о рекламодателе
     * В противном случае null.
     */
    public function getAdvertiserInfo(): ?array
    {
        if ($this->advertiserInfo) {
            return $this->advertiserInfo;
        }

        $api = $this->container->getApi()->authorize();

        if (!$api->isAuthorized()) {
            return null;
        }

        $result = $api->get('/advertiser/info/');

        return $this->advertiserInfo = reset($result);
    }

    /**
     * Отправляет postback запрос для заказа с идентификатором $orderId.
     */
    public function sendPostback(int $orderId): void
    {
        $data = $this->container->getParameters()->getPostbackData($orderId);

        $campaignCode = $this->container->getSettings()->get('main', 'campaign_code');
        $postbackKey = $this->container->getSettings()->get('main', 'postback_key');

        $this->doSendPostback($campaignCode, $postbackKey, $data['order_id'], $data['positions']);
    }

    /**
     * Обрабатывает параметры get-запроса и в случае наличия admitad uid привязывает его к пользоателю.
     */
    public function handleUser(): void
    {
        $paramName = $this->container->getSettings()->getParamName();

        if (!isset($_GET[$paramName])) {
            return;
        }

        $uid = $_GET[$paramName];

        $this->container->getParameters()->setUserId($uid);
    }

    public function handleGclid(): void
    {
        $paramName = 'gclid';

        if (!isset($_GET[$paramName])) {
            return;
        }

        $gclid = $_GET[$paramName];

        $this->container->getParameters()->setGclid($gclid);
    }

    /**
     * Возвращает текущий admitad uid.
     */
    public function getUserId(): mixed
    {
        if ($uid = $this->container->getParameters()->getUserId()) {
            return $uid;
        }

        $name = $this->container->getSettings()->getCookieName();

        if (!isset($_COOKIE[$name])) {
            return null;
        }

        return $_COOKIE[$name];
    }

    public function getGclid(): ?string
    {
        if ($uid = $this->container->getParameters()->getGclid()) {
            return $uid;
        }

        $name = '_tagtag_gclid';

        if (!isset($_COOKIE[$name])) {
            return null;
        }

        return $_COOKIE[$name];
    }

    public function isCrossDevice(): bool
    {
        $name = $this->container->getSettings()->getCookieName();

        if (isset($_COOKIE[$name])) {
            return false;
        }

        $userId = get_current_user_id();

        if ($userId && $uid = get_user_meta($userId, 'admitad_uid', true)) {
            return true;
        }

        return false;
    }

    public function getRetag(): AdmitadRetag
    {
        return new AdmitadRetag($this->container->getParameters());
    }

    /**
     * Отправляет postback запросы с указанными позициями и параметрами
     * Указанное в $parameters попадает в каждую отправленную позицию
     * В $positions содержится массив вида ['product_id' => '', 'price' => '', 'quantity' => ''].
     */
    protected function doSendPostback(
        string $campaignCode,
        string $postbackKey,
        int $orderId,
        array $positions,
        array $parameters = []
    ): void {
        $positions = array_values($positions);
        $uid = $this->getUserId();
        $gclid = $this->getGclid();

        $defaults = [
            'payment_type' => 'sale',
            'tariff_code' => 1,
            'currency_code' => $this->container->getSettings()->get('main', 'currency_code'),
        ];

        $global = array_merge(
            [
                'campaign_code' => $campaignCode,
                'postback' => true,
                'postback_key' => $postbackKey,
                'response_type' => 'img',
                'action_code' => '1',
                'adm_method' => 'plugin',
                'adm_method_name' => 'wordpress',
                'action_useragent' => wc_get_user_agent(),
                'channel' => 'adm',
                'adm_source' => 'cookie',
            ],
            $parameters
        );

        if ($gclid) {
            $global['gclid'] = $gclid;
        }

        if ($this->isCrossDevice()) {
            $global['adm_source'] = 'crossdevice';
        }

        $admitadPositions = $this->generatePositions(
            $uid,
            $orderId,
            $positions,
            array_merge($global, $defaults)
        );

        foreach ($admitadPositions as $position) {
            $parts = [];

            if (null === $position['action_code'] || null === $position['tariff_code']) {
                continue;
            }

            foreach ($position as $key => $value) {
                $parts[] = $key . '=' . urlencode((string) $value);
            }

            $url = 'https://ad.admitad.com/tt?' . implode('&', $parts);

            if (!function_exists('curl_init')) {
                file_get_contents($url);

                continue;
            }

            $cl = curl_init($url);

            curl_setopt($cl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($cl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($cl, CURLOPT_RETURNTRANSFER, true);

            curl_exec($cl);
        }
    }

    /**
     * Преобразует массив $positions вида ['product_id' => '', 'price' => '', 'quantity' => '']
     * в массив с параметрами необходимые для запросов к admitad.
     */
    protected function generatePositions(
        string $uid,
        int $orderId,
        array $positions,
        array $parameters = []
    ): array {
        $config = array_merge(
            [
                'uid' => $uid,
                'order_id' => $orderId,
                'position_count' => count($positions),
            ],
            $parameters
        );

        foreach ($positions as $index => &$position) {
            $position = array_merge($config, ['position_id' => $index + 1], $position);
        }

        return $positions;
    }
}
