<?php

namespace InstagramAPI\Realtime\Subscription\GraphQl;

use InstagramAPI\Realtime\Subscription\GraphQlSubscription;
use InstagramAPI\Signatures;

class LiveRealtimeCommentsSubscription extends GraphQlSubscription
{
    const QUERY = '17855344750227125';
    const ID = 'live_realtime_comments';

    /**
     * Constructor.
     *
     * @param string $deviceId
     */
    public function __construct(
        $subscriptionId,
        $broadcastId)
    {
        parent::__construct(self::QUERY, [
            'client_subscription_id' => $subscriptionId,
            'broadcast_id' => $broadcastId
        ]);
    }

    /** {@inheritdoc} */
    public function getId()
    {
        return self::ID;
    }
}
