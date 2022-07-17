<?php

namespace InstagramAPI\Realtime\Subscription\GraphQl;

use InstagramAPI\Realtime\Subscription\GraphQlSubscription;
use InstagramAPI\Signatures;

class DirectStatusSubscription extends GraphQlSubscription
{
    const QUERY = '17854499065530643';
    const ID = 'direct_status';

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
