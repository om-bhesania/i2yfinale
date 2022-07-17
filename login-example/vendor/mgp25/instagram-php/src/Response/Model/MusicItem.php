<?php

namespace InstagramAPI\Response\Model;

use InstagramAPI\AutoPropertyMapper;

/**
 * MusicItem.
 *
 * @method Artist        getArtist()
 * @method Track         getTrack()
 * @method MusicMetadata getMusicMetadata()
 * @method bool          isArtist()
 * @method bool          isTrack()
 * @method bool          isMusicMetadata()
 * @method $this         setArtist(Artist $value)
 * @method $this         setTrack(Track $value)
 * @method $this         setMusicMetadata(MusicMetadata $value)
 * @method $this         unsetArtist()
 * @method $this         unsetTrack()
 * @method $this         unsetMusicMetadata()
 */
class MusicItem extends AutoPropertyMapper
{
    const JSON_PROPERTY_MAP = [
        'artist' => 'Artist',
        'track' => 'Track',
        'metadata' => 'MusicMetadata'
    ];
}
