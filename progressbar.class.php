<?php

/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2014, Maciej Szkamruk <ex3v@ex3v.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * ProgressBar - class that helps you display pretty progress bar with 
 * ETA while executing php scripts in command line.
 * 
 * To use, simply initialize it before loop, providing amount of steps in constructor:
 * <code>
 * $progressBar = new ProgressBar(300);
 * </code>
 * 
 * While in loop, call below command to display current progress:
 * 
 * <code>
 * foreach(...){
 *      echo $progressBar->drawCurrentProgress();
 * }
 * </code>
 *
 * Any modifications are warmly welcome. Especially modifications 
 * that will make calculating ETA more adaptive - for now it works 
 * best for loops with more or less equal time between steps.
 * 
 * Have fun!
 * 
 * @author Maciej Szkamruk <ex3v@ex3v.com>
 */
class ProgressBar {

  private $currentProgress;
  private $endProgress;
  private $currentTime;
  private $progressChar;
  private $progressCharColor;
  private $progressTrack;
  private $progressTrackColor;

  /**
   * Constructor. Requires <strong>$endProgress</strong> param. 
   * This param should be Integer value indicating, how many iterations 
   * will loop, that this progress bar is used in, contain.
   * 
   * @param integer $endProgress end progress
   * @throws InvalidArgumentException
   */
  public function __construct(
    $endProgress,
    $progressChar       = "=",
    $progressTrack      = " ",
    $progressCharColor  = "default",
    $progressTrackColor = "default"
  ) {
    if (!is_numeric($endProgress) || $endProgress < 1) {
      throw new InvalidArgumentException('Provided end progress value should be numeric.');
    }

    $this->endProgress = $endProgress;
    $this->currentTime = microtime(true);
    $this->progressChar = $progressChar;
    $this->progressTrack = $progressTrack;
    $this->progressCharColor = $progressCharColor;
    $this->progressTrackColor = $progressTrackColor;
  }

  /**
   * Returns current progress. <strong>$currentProgress</strong> 
   * parameter is optional. If not provided, current progress 
   * will be incremented by one.
   * 
   * @param int $currentProgress
   * @return string
   * @throws InvalidArgumentException
   */
  public function drawCurrentProgress($currentProgress = null) {
    if ($currentProgress !== null) {
      if ($currentProgress < $this->currentProgress) {
        throw new InvalidArgumentException("Provided current progress is smaller than previous one.");
      }
      else {
        $this->currentProgress = $currentProgress;
      }
    }
    else {
      $this->currentProgress++;
    }

    $progress = $this->currentPercentage();
    $maxWidth = $this->getTerminalWidth();
    $etaNum = $this->getETA($progress);

    return $this->buildBar($progress, $maxWidth, $etaNum);
  }

  private function color(string $color, string $text) {
    $colors = [
      "black"         => "\033[0;30m",
      "red"           => "\033[0;31m",
      "light-red"     => "\033[1;31m",
      "green"         => "\033[0;32m",
      "light-green"   => "\033[1;32m",
      "brown"         => "\033[0;33m",
      "orange"        => "\033[0;33m",
      "blue"          => "\033[0;34m",
      "light-blue"    => "\033[1;34m",
      "purple"        => "\033[0;35m",
      "light-purple"  => "\033[1;35m",
      "cyan"          => "\033[0;36m",
      "light-cyan"    => "\033[1;36m",
      "light-gray"    => "\033[0;37m",
      "dark-gray"     => "\033[1;30m",
      "yellow"        => "\033[1;33m",
      "white"         => "\033[1;37m",
      "default"       => "\033[0m",
    ];
  
    if (!array_key_exists($color, $colors)) $color = $colors["default"];
  
    return $colors[$color] . "{$text}" . $colors['default'];
  }

  /**
   * Calculates current percentage
   * @return int
   */
  private function currentPercentage() {
    $progress = $this->currentProgress / $this->endProgress;

    return $progress * 100;
  }

  /**
   * Builds progress bar row using provided data
   * 
   * @param int $progress
   * @param int $maxWidth
   * @param string $etaNum
   * @return string
   */
  private function buildBar($progress, $maxWidth, $etaNum) {
    $eta = $etaNum ? '(ETA: ' . $etaNum . ')' : '';
    $percentage = number_format($progress, 2) . "%";
    $widthLeft = $maxWidth - 1 - strlen($eta) - 1 - strlen($percentage) - 2;
    $prgDone = ceil($widthLeft * ($progress / 100));
    $prgNotDone = $widthLeft - $prgDone;
    $out = "[" . str_repeat($this->color($this->progressCharColor, $this->progressChar), $prgDone) . str_repeat($this->color($this->progressTrackColor, $this->progressTrack), $prgNotDone) . '] ' . $percentage . ' ' . $eta;

    return "\r{$out}";
  }

  /**
   * Returns terminal width
   * 
   * @return int
   */
  private function getTerminalWidth() {
    switch (PHP_OS_FAMILY) {
      case "Windows":
        return (int) exec('%SYSTEMROOT%\System32\WindowsPowerShell\v1.0\powershell.exe -Command "$Host.UI.RawUI.WindowSize.Width"');
      case 'Darwin':
        return (int) exec('/bin/stty/ size | cut -d" " -f2');
      case 'Linux':
        return (int) exec('/usr/bin/env tput cols');
      default:
        return 0;
    }
  }

  /**
   * Calculates and returns ETA with human timing formatting
   * 
   * @param int $progress
   * @return string
   */
  private function getETA($progress) {
    $currTime = microtime(true);

    if (!$progress || $progress <= 0 || $progress === false) {
      return "";
    }

    try {
      $etaTime = (($currTime - $this->currentTime) / $progress) * (100 - $progress);
      $diff = ceil($etaTime);
      $eta = $this->humanTiming($diff);
    }
    catch (Exception $ex) {
      $eta = '';
    }

    return $eta;
  }

  /**
   * Converts numeric time to human-readable format
   * 
   * @param int $time
   * @return string
   */
  private function humanTiming($time) {
    $tokens = [
      31536000 => ['year', 'years'],
      2592000 => ['month', 'months'],
      604800 => ['week', 'weeks'],
      86400 => ['day', 'days'],
      3600 => ['hour', 'hours'],
      60 => ['minute', 'minutes'],
      1 => ['second', 'seconds']
    ];

    $result = '';

    foreach ($tokens as $unit => $labels) {
      if ($time < $unit) continue;

      $numberOfUnits = floor($time / $unit);
      $label = $numberOfUnits === 1 ? $labels[0] : $labels[1];

      $result .= ($result ? ' ' : '') . $numberOfUnits . ' ' . $label;
      $time -= $numberOfUnits * $unit;
    }

    return $result ?: '0 seconds';
  }

}
