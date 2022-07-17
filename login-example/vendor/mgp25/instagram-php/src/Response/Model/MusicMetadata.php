<?php

namespace InstagramAPI\Response\Model;

use InstagramAPI\AutoPropertyMapper;

/**
 * MusicMetadata.
 *
 * @method bool getIsBookmarked()
 * @method bool isBookmarked()
 * @method $this setIsBookmarked(bool $value)
 * @method $this unsetIsBookmarked()
 */
class MusicMetadata extends AutoPropertyMapper
{
    const JSON_PROPERTY_MAP = [
        'is_bookmarked' => 'bool',
    ];
}
