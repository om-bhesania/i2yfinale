<?php

namespace InstagramAPI\Realtime\Subscription\GraphQl;

use InstagramAPI\Realtime\Subscription\GraphQlSubscription;
use InstagramAPI\Signatures;

class AsyncAdSubscription extends GraphQlSubscription
{
    const QUERY = '17911191835112000';
    const ID = 'async_ad';

    /**
     * Constructor.
     *
     * @param string $deviceId
     */
    public function __construct(
        $subscriptionId,
        $deviceId)
    {
        parent::__construct(self::QUERY, [
            'client_subscription_id' => $subscriptionId,
            'device_id'              => $deviceId,
        ]);
    }

    /** {@inheritdoc} */
    public function getId()
    {
        return self::ID;
    }
}
