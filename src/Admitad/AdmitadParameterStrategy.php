<?php

declare(strict_types=1);

namespace Admitad;

use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WP_User_Query;

class AdmitadParameterStrategy
{
    public const ACTION_TYPE_NONE = '0';

    public const ACTION_TYPE_SALE = '1';

    public const USER_TYPE_NONE = '0';

    public const USER_TYPE_NEW = '1';

    public const USER_TYPE_OLD = '2';

    public function __construct(protected AdmitadContainer $container) {}

    /**
     * Возвращает по order_id необходимую информацию для формирования postback запросов
     * В виде ['order_id' => '', 'positions' => ['product_id' => '', 'price' => '', 'quantity' => '']].
     *
     * @param mixed $orderId
     */
    public function getPostbackData($orderId): array
    {
        $order = new \WC_Order($orderId);
        $positions = [];

        $coupon = '';
        $coupons = $order->get_coupon_codes();

        if (!empty($coupons[0])) {
            $coupon = $coupons[0];
        }

        foreach ($order->get_items() as $item) {
            $productId = $item['product_id'];
            $quantity = $item['qty'];

            $product = new \WC_Product($productId);
            $price = $product->get_price();

            $code = $this->container->getParameters()->getActionTariffCode($order, $item);

            $positionConfig = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
                'action_code' => $code['action_code'],
                'tariff_code' => $code['tariff_code'],
            ];

            if ('' !== $coupon) {
                $positionConfig['promocode'] = $coupon;
            }

            $positions[] = $positionConfig;
        }

        return [
            'order_id' => $orderId,
            'positions' => $positions,
        ];
    }

    public function getCheckoutRetagParams(): array
    {
        return [
            'ad_order' => '',
            'ad_amount' => 0,
            'ad_products' => [
                [
                    'id' => '',
                    'number' => 1,
                ],
            ],
        ];
    }

    public function getCartRetagParams(): array
    {
        return [
            'ad_products' => [
                ['id' => '', 'number' => 1],
            ],
        ];
    }

    public function getProductRetagParams(): array
    {
        return [
            'ad_product' => [
                'id' => '',
                'vendor' => '',
                'price' => '',
                'url' => '',
                'picture' => '',
                'name' => '',
                'category' => '',
            ],
        ];
    }

    public function getCategoryRetagParams(): array
    {
        return ['ad_category' => ''];
    }

    /**
     * Возвращает admitad uid.
     */
    public function getUserId(): ?string
    {
        $cookieName = $this->container->getSettings()->getCookieName();

        if (isset($_COOKIE[$cookieName])) {
            return $_COOKIE[$cookieName];
        }

        $userId = get_current_user_id();

        if ($userId && $uid = get_user_meta($userId, 'admitad_uid', true)) {
            return $uid;
        }

        return null;
    }

    /**
     * Возвращает gclid.
     */
    public function getGclid(): ?string
    {
        $cookieName = '_tagtag_gclid';

        if (isset($_COOKIE[$cookieName])) {
            return $_COOKIE[$cookieName];
        }

        $userId = get_current_user_id();

        if ($userId && $uid = get_user_meta($userId, '_tagtag_gclid', true)) {
            return $uid;
        }

        return null;
    }

    /**
     * Привязывает admitad uid, содержащийся в параметре $uid, к пользователю.*.
     */
    public function setUserId(string $uid): void
    {
        $lifeTime = 90 * 60 * 60 * 24;
        $cookieName = $this->container->getSettings()->getCookieName();

        setcookie($cookieName, $uid, ['expires' => time() + $lifeTime, 'path' => '/']);

        if (isset($_SERVER['HTTP_HOST'])) {
            setcookie($cookieName, $uid, [
                'expires' => time() + $lifeTime,
                'path' => '/', 'domain' => '.' . $_SERVER['HTTP_HOST'],
            ]);
        }

        if ($userId = get_current_user_id()) {
            update_user_meta($userId, 'admitad_uid', $uid);
        }
    }

    /**
     * Привязывает gclid, содержащийся в параметре $uid, к пользователю.*.
     */
    public function setGclid(string $gclid): void
    {
        $lifeTime = 90 * 60 * 60 * 24;
        $cookieName = '_tagtag_gclid';

        setcookie(
            $cookieName,
            $gclid,
            [
                'expires' => time() + $lifeTime,
                'path' => '/']
        );

        if (isset($_SERVER['HTTP_HOST'])) {
            setcookie($cookieName, $gclid, [
                'expires' => time() + $lifeTime,
                'path' => '/', 'domain' => '.' . $_SERVER['HTTP_HOST'],
            ]);
        }

        if ($userId = get_current_user_id()) {
            update_user_meta($userId, '_tagtag_gclid', $gclid);
        }
    }

    public function getActionTariffCode(\WC_Order $order, \WC_Order_Item_Product $item): array
    {
        $cats = $this->getProductCategories($item['product_id']);
        $orderSum = $order->get_total() - $order->get_shipping_total();

        $orderData = [
            'id' => $order->get_id(),
            'sum' => $orderSum,
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'promocode' => !empty($order->get_coupon_codes()),
        ];

        $itemData = ['categories' => $cats];

        return $this->getTariffCode($itemData, $orderData);
    }

    /**
     * Возвращает массив с категориями товара.
     */
    protected function getProductCategories(int $productId): array
    {
        $rows = wp_get_post_terms($productId, 'product_cat');

        return array_map(fn ($row) => $row->term_id, $rows);
    }

    /**
     * Возвращает action_code и tariff_code по данным в orderData и itemData исходя из указанных настроек.
     */
    protected function getTariffCode(array $itemData, array $orderData): array
    {
        $optionName = $orderData['promocode'] ? 'promo_actions' : 'actions';
        $map = $this->container->getSettings()->get($optionName, 'actions') ?: [];

        // Проверка что есть настроенный конфиг для старого пользователя
        $oldUserAvailable = false;

        foreach ($map as $actionData) {
            if (
                self::USER_TYPE_OLD === $actionData['user_type']
                && self::ACTION_TYPE_SALE === $actionData['type']
            ) {
                $oldUserAvailable = true;

                break;
            }
        }

        $tariff = null;
        $code = null;

        foreach ($map as $actionCode => $actionData) {
            if (!$this->isOrderAvailable($orderData, $actionData, $oldUserAvailable)) {
                continue;
            }

            foreach ($actionData['tariffs'] as $tariffCode => $tariffData) {
                if (null !== $tariff && !$this->compareTariffs($tariffData, $tariff, $itemData)) {
                    continue;
                }

                $code = [
                    'action_code' => $actionCode,
                    'tariff_code' => $tariffCode,
                ];
                $tariff = $tariffData;
            }
        }

        return $code ?: [
            'action_code' => null,
            'tariff_code' => null,
        ];
    }

    /**
     * Проверяет принадлежность заказа к фильтру действия (цена, старый/новый пользователь, тип "покупка").
     */
    protected function isOrderAvailable(
        array $orderData,
        array $actionFilter,
        bool $oldUserAvailable
    ): bool {
        if (self::ACTION_TYPE_SALE !== $actionFilter['type']) {
            return false;
        }
        $isNewUser = $this->isNewUser($orderData);
        $userType = $actionFilter['user_type'];

        if (
            $oldUserAvailable
            && $isNewUser
            && self::USER_TYPE_NEW !== $userType
            && self::USER_TYPE_NONE !== $userType
        ) {
            return false;
        }

        if (
            $oldUserAvailable
            && !$isNewUser
            && self::USER_TYPE_OLD !== $userType
            && self::USER_TYPE_NONE !== $userType
        ) {
            return false;
        }

        if (!empty($actionFilter['price_from']) || !empty($actionFilter['price_to'])) {
            $price = $orderData['sum'];
            $priceFrom = $actionFilter['price_from'] ?? 0;
            $priceTo = $actionFilter['price_to'] ?? 0;

            if ($price < $priceFrom) {
                return false;
            }

            if ($price > $priceTo && $priceTo >= $priceFrom) {
                return false;
            }
        }

        return true;
    }

    /**
     * Проверяет по данным заказа и информации о текущем пользователе на старого и нового пользователя.
     */
    protected function isNewUser(array $orderData): bool
    {
        $userId = null;
        $args = [
            'role' => 'customer',
            'order' => 'asc',
            'orderby' => 'id',
            'meta_query' => [
                'relation' => 'or',
                [
                    'key' => 'billing_email',
                    'value' => $orderData['email'],
                    'compare' => '=',
                ],
                [
                    'key' => 'billing_phone',
                    'value' => $orderData['phone'],
                    'compare' => '=',
                ],
            ],
        ];

        if ($userId = get_current_user_id()) {
            $args['meta_query'][] = [
                'key' => 'user_id',
                'value' => $userId,
                'compare' => '=',
            ];
        }
        $userQuery = new \WP_User_Query($args);
        $results = $userQuery->get_results();

        if (count($results) > 0) {
            $userId = $results[0]->ID;
        }

        if (null === $userId) {
            return true;
        }

        $args = [
            'customer_id' => $userId,
            'status' => ['wc-completed'],
        ];

        if (count(wc_get_orders($args)) > 0) {
            return false;
        }

        return true;
    }

    /**
     * В случае, если оба тарифа подходят,
     * возвращает true если тариф $left актуальнее тарифа $right, false в противном случае.
     */
    protected function compareTariffs(array $left, array $right, array $filter): bool
    {
        $leftCategories = $left['categories'];
        $rightCategories = $right['categories'];

        foreach (array_reverse($filter['categories']) as $categoryId) {
            if (false !== array_search($categoryId, $leftCategories)) {
                return true;
            }

            if (false !== array_search($categoryId, $rightCategories)) {
                return false;
            }
        }

        if (!$leftCategories && $rightCategories) {
            return true;
        }

        if (!$rightCategories && $leftCategories) {
            return false;
        }

        return false;
    }
}
