<?php

namespace App\Base;

class Image
{
    const CROP_TOP = 1;
    const CROP_CENTER = 2;
    const CROP_BOTTOM = 3;
    const CROP_LEFT = 4;
    const CROP_RIGHT = 5;
    private $image;
    private ?int $image_type;
 

    public function __construct(string $filename)
    { 
        list($width, $height, $type, $attr) = getimagesize($filename);
      
        $this->image_type = $type;
      
        if ($this->image_type === IMAGETYPE_JPEG) {
            $this->image = imagecreatefromjpeg($filename);
        } elseif($this->image_type === IMAGETYPE_GIF) {
            $this->image = imagecreatefromgif($filename);
        } elseif($this->image_type === IMAGETYPE_PNG) {
            $this->image = imagecreatefrompng($filename);
        }
    }

    public function getContents(?int $image_type = null) {
        ob_start();

        $this->output($image_type);

        $contents = ob_get_clean();

        return $contents;
    }

    public function getMimeType(): ?string
    {
        if ($this->image_type === IMAGETYPE_JPEG) {
            return 'image/jpeg';
        } elseif($this->image_type === IMAGETYPE_GIF) {
            return 'image/gif';
        } elseif($this->image_type === IMAGETYPE_PNG) {
            return 'image/png';
        }

        return null;
    }

    public function getWidth(): int
    {
        return imagesx($this->image);
    }

    public function getHeight(): int
    {
        return imagesy($this->image);
    }

    public function output(?int $image_type = null) {
        if ($image_type === null) {
            $image_type = $this->image_type;
        }

        if ($image_type === IMAGETYPE_JPEG) {
            imagejpeg($this->image);
        } elseif ($image_type === IMAGETYPE_GIF) {
            imagegif($this->image);
        } elseif ($image_type === IMAGETYPE_PNG) {
            imagepng($this->image);
        }
    }

    public function calculateWidth(int $height): int
    {
        $ratio = $height / $this->getHeight();

        return round($this->getWidth() * $ratio);
    }
 
    public function calculateHeight(int $width): int
    {
        $ratio = $width / $this->getWidth();

        return round($this->getHeight() * $ratio);
    }
 
    public function scale(int $scale): void
    {
        $width = $this->getWidth() * $scale / 100;
        $height = $this->getheight() * $scale / 100;

        $this->resize($width, $height);
    }

    public function crop(
        int $width,
        int $height,
        ?bool $allow_enlarge = false,
        ?int $position = self::CROP_CENTER
    ): void
    {
        if (!$allow_enlarge) {
            if ($width > $this->getWidth()) {
                $width  = $this->getWidth();
            }

            if ($height > $this->getHeight()) {
                $height = $this->getHeight();
            }
        }

        $ratio = $this->getWidth() / $this->getHeight();
        $new_ratio = $width / $height;
        $new_x = 0;
        $new_y = 0;

        if ($new_ratio < $ratio) {
            $new_height = $height;
            $new_width = $this->calculateWidth($height);
            $new_x = $this->getCropPosition($width - $new_width, $position);
        } else {
            $new_width = $width;
            $new_height = $this->calculateHeight($width);
            $new_y = $this->getCropPosition($height - $new_height, $position);
        }

        $new_image = imagecreatetruecolor($width, $height);

        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        imagecopyresampled(
            $new_image,
            $this->image,
            $new_x,
            $new_y,
            0,
            0,
            $new_width,
            $new_height,
            $this->getWidth(),
            $this->getHeight()
        );

        $this->image = $new_image;
    }
 
    public function resize(int $width, int $height, bool $allow_enlarge = false): void
    {
        if (!$allow_enlarge) {
            if ($width > $this->getWidth() || $height > $this->getHeight()) {
                $width  = $this->getWidth();
                $height = $this->getHeight();
            }
        }

        $new_image = imagecreatetruecolor($width, $height);

        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        imagecopyresampled(
            $new_image,
            $this->image,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $this->getWidth(),
            $this->getHeight()
        );

        $this->image = $new_image;
    }

    public function resizeToShortSide(int $max_short, ?bool $allow_enlarge = false): void
    {
        if ($this->getHeight() < $this->getWidth()) {
            $ratio = $max_short / $this->getHeight();
            $long = round($this->getWidth() * $ratio);

            $this->resize($long, $max_short, $allow_enlarge);
        } else {
            $ratio = $max_short / $this->getWidth();
            $long = round($this->getHeight() * $ratio);

            $this->resize($max_short, $long, $allow_enlarge);
        }
    }

    public function resizeToLongSide(int $max_long, ?bool $allow_enlarge = false): void
    {
        if ($this->getHeight() > $this->getWidth()) {
            $ratio = $max_long / $this->getHeight();
            $short = round($this->getWidth() * $ratio);

            $this->resize($short, $max_long, $allow_enlarge);
        } else {
            $ratio = $max_long / $this->getWidth();
            $short = round($this->getHeight() * $ratio);

            $this->resize($max_long, $short, $allow_enlarge);
        }
    }


    protected function getCropPosition(int $expected_size, ?int $position = self::CROP_CENTER): int
    {
        $size = 0;

        switch ($position) {
            case self::CROP_BOTTOM:
            case self::CROP_RIGHT:
                $size = $expected_size;
                break;
            case self::CROP_CENTER:
                $size = $expected_size / 2;
                break;
        }

        return round($size);
    }
}