<?php

namespace InstagramAPI\Realtime\Subscription\GraphQl;

use InstagramAPI\Realtime\Subscription\GraphQlSubscription;
use InstagramAPI\Signatures;

class ClientConfigUpdateSubscription extends GraphQlSubscription
{
    const QUERY = '17849856529644700';
    const ID = 'client_config_update';

    /**
     * Constructor.
     *
     * @param string $deviceId
     */
    public function __construct(
        $subscriptionId)
    {
        parent::__construct(self::QUERY, [
            'client_subscription_id' => $subscriptionId
        ]);
    }

    /** {@inheritdoc} */
    public function getId()
    {
        return self::ID;
    }
}
