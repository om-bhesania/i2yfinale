<?php

namespace InstagramAPI\Response\Model;

use InstagramAPI\AutoPropertyMapper;

/**
 * ClipsFeedItem.
 *
 * @method Item getMedia()
 * @method bool isMedia()
 * @method $this setMedia(Item $value)
 * @method $this unsetMedia()
 */
class ClipsFeedItem extends AutoPropertyMapper
{
    const JSON_PROPERTY_MAP = [
        'media' => 'Item',
    ];
}
