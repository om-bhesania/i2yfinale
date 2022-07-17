<?php

namespace InstagramAPI\Realtime\Payload;

use InstagramAPI\AutoPropertyMapper;

/**
 * IrisSubscribeAck.
 *
 * @method string getErrorMessage()
 * @method int getErrorType()
 * @method int getSeqId()
 * @method string getSnapshotAtMs()
 * @method string getSnapshotAppVersion()
 * @method bool getSucceeded()
 * @method bool isErrorMessage()
 * @method bool isErrorType()
 * @method bool isSeqId()
 * @method bool isSnapshotAtMs()
 * @method bool isSnapshotAppVersion()
 * @method bool isSucceeded()
 * @method $this setErrorMessage(string $value)
 * @method $this setErrorType(int $value)
 * @method $this setSeqId(int $value)
 * @method $this setSnapshotAtMs(string $value)
 * @method $this setSnapshotAppVersion(string $value)
 * @method $this setSucceeded(bool $value)
 * @method $this unsetErrorMessage()
 * @method $this unsetErrorType()
 * @method $this unsetSeqId()
 * @method $this unsetSnapshotAtMs()
 * @method $this unsetSnapshotAppVersion()
 * @method $this unsetSucceeded()
 */
class IrisSubscribeAck extends AutoPropertyMapper
{
    const JSON_PROPERTY_MAP = [
        'seq_id'        => 'int',
        'snapshot_at_ms' => 'string',
        'snapshot_app_version' => 'string',
        'succeeded'     => 'bool',
        'error_type'    => 'int',
        'error_message' => 'string',
    ];
}
