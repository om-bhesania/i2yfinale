<?php

namespace InstagramAPI\Media\Constraints;

use InstagramAPI\Media\ConstraintsInterface;

/**
 * Instagram's clip media constraints.
 */
class ClipConstraints implements ConstraintsInterface
{
    /**
     * Lowest allowed clip aspect ratio.
     *
     * This range was decided through community research, which revealed that
     * all Instagram clips are in ~9:16 (0.5625, widescreen portrait) ratio,
     * with a small range of similar portrait ratios also being used sometimes.
     *
     * @var float
     */
    const MIN_RATIO = 0.56;

    /**
     * Highest allowed clip aspect ratio.
     *
     * This range was decided through community research.
     * 
     * @var float
     */
    const MAX_RATIO = 0.67;

    /**
     * The recommended clip aspect ratio.
     * 
     * @var float
     */
    const RECOMMENDED_RATIO = 0.5625;

    /**
     * The deviation for the recommended aspect ratio.
     *
     * @var float
     */
    const RECOMMENDED_RATIO_DEVIATION = 0.0025;

    /**
     * Minimum allowed video duration.
     *
     * @var float
     */
    const MIN_DURATION = 0.5;

    /**
     * Maximum allowed video duration.
     *
     * @var float
     */
    const MAX_DURATION = 60.0;

    /** {@inheritdoc} */
    public function getTitle()
    {
        return 'clip';
    }

    /** {@inheritdoc} */
    public function getMinAspectRatio()
    {
        return self::MIN_RATIO;
    }

    /** {@inheritdoc} */
    public function getMaxAspectRatio()
    {
        return self::MAX_RATIO;
    }

    /** {@inheritdoc} */
    public function getRecommendedRatio()
    {
        return self::RECOMMENDED_RATIO;
    }

    /** {@inheritdoc} */
    public function getRecommendedRatioDeviation()
    {
        return self::RECOMMENDED_RATIO_DEVIATION;
    }

    /** {@inheritdoc} */
    public function useRecommendedRatioByDefault()
    {
        return true;
    }

    /** {@inheritdoc} */
    public function getMinDuration()
    {
        return self::MIN_DURATION;
    }

    /** {@inheritdoc} */
    public function getMaxDuration()
    {
        return self::MAX_DURATION;
    }
}
