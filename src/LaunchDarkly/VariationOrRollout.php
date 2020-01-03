<?php

namespace LaunchDarkly;

class VariationOrRollout
{
    private static $LONG_SCALE = 0xFFFFFFFFFFFFFFF;

    /** @var int | null */
    private $_variation = null;
    /** @var Rollout | null */
    private $_rollout = null;

    protected function __construct($variation, $rollout)
    {
        $this->_variation = $variation;
        $this->_rollout = $rollout;
    }

    public static function getDecoder()
    {
        return function ($v) {
            return new VariationOrRollout(
                isset($v['variation']) ? $v['variation'] : null,
                isset($v['rollout']) ? call_user_func(Rollout::getDecoder(), $v['rollout']) : null);
        };
    }

    /**
     * @return int | null
     */
    public function getVariation()
    {
        return $this->_variation;
    }

    /**
     * @return Rollout | null
     */
    public function getRollout()
    {
        return $this->_rollout;
    }

    /**
     * @param $user LDUser
     * @param $_key string
     * @param $_salt string
     * @return int|null
     */
    public function variationIndexForUser($user, $_key, $_salt)
    {
        if ($this->_variation !== null) {
            return $this->_variation;
        }
        $rollout = $this->_rollout;
        if ($rollout === null) {
            return null;
        }
        $variations = $rollout->getVariations();
        if ($variations) {
            $bucketBy = $this->_rollout->getBucketBy() === null ? "key" : $this->_rollout->getBucketBy();
            $bucket = self::bucketUser($user, $_key, $bucketBy, $_salt);
            $sum = 0.0;
            foreach ($variations as $wv) {
                $sum += $wv->getWeight() / 100000.0;
                if ($bucket < $sum) {
                    return $wv->getVariation();
                }
            }
            return $variations[count($variations) - 1]->getVariation();
        }
        return null;
    }

    /**
     * @param $user LDUser
     * @param $_key string
     * @param $attr string
     * @param $_salt string
     * @return float
     */
    public static function bucketUser($user, $_key, $attr, $_salt)
    {
        $userValue = $user->getValueForEvaluation($attr);
        $idHash = null;
        if ($userValue != null) {
            if (is_int($userValue)) {
                $userValue = (string) $userValue;
            }
            if (is_string($userValue)) {
                $idHash = $userValue;
                if ($user->getSecondary() !== null) {
                    $idHash = $idHash . "." . strval($user->getSecondary());
                }
                $hash = substr(sha1($_key . "." . $_salt . "." . $idHash), 0, 15);
                $longVal = base_convert($hash, 16, 10);
                $result = $longVal / self::$LONG_SCALE;

                return $result;
            }
        }
        return 0.0;
    }
}
