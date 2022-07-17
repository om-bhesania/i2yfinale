<?php

namespace InstagramAPI\Realtime\Subscription\GraphQl;

use InstagramAPI\Realtime\Subscription\GraphQlSubscription;
use InstagramAPI\Signatures;

class InteractivityRealtimeQuestionSubmissionsStatusSubscription extends GraphQlSubscription
{
    const QUERY = '18027779584026952';
    const ID = 'interactivity_realtime_question_submissions_status';

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
