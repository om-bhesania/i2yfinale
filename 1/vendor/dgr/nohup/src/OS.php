<?php
/**
 * Created by Cartman Chen <me@csz.link>.
 * Author: 陈章--大官人
 * Github: https://github.com/cartmanchen
 */

namespace dgr\nohup;

class OS
{
    public static function isWin()
    {
        return substr(strtoupper(PHP_OS), 0, 3) === 'WIN';
    }
}
