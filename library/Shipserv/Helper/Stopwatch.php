<?php
/**
 * Helper class for easier profiling and debug logging
 *
 * Usage:
 *
 * $t = new Shipserv_Utils_Stopwatch();
 * ...
 * $t->click();
 * // run profiled code
 * $elapsed = $t->click('note');
 * ...
 * writeToLog('Performance stats: ' . print_r($t->getLoops(), true));
 *
 * @author  Yuriy Akopov
 * @date    2013-08-01
 */
class Shipserv_Helper_Stopwatch {
    const
        TOTAL_KEY = 'Total' // name for pseudo-loop with the sum of all recorded times
    ;

    /**
     * Time the current loop started or null if in idle state
     *
     * @var null|float
     */
    protected $start = null;

    /**
     * Elapsed time for loops recorded previously
     *
     * @var array
     */
    protected $loops = array();

    public function __construct($click = false) {
        if ($click) {   // start timer straight away making the surrounding profiled code one line less cluttered
            $this->click();
        }
    }

    /**
     * This function implements two distinct behaviours for the same of surrounding profiled code simplicity and
     * resemblance with real-life stopwatches
     *
     * When called in idle, starts the time recording; when called in recording state, switches to idle after writing down elapsed time
     *
     * @param   string  $loop
     * @param   bool    $print
     *
     * @return  float|null      null if timer was started, elapsed time if it was stopped
     */
    public function click($loop = null, $print = false) {

        if (is_null($this->start)) {
            // we aren't recording now, so start doing that
            $this->start = microtime(true);
            return null;
        }

        $elapsed = microtime(true) - $this->start;
        $this->start = null;

        if (is_null($loop)) {
            $this->loops[] = $elapsed;
            $loop = count($this->loops);
        } else {
            $this->loops[$loop] = $elapsed;
        }

        if ($print) {
            printf("<b>%s:</b> %.3f sec<br/>\n", $loop, $elapsed);
        }

        return $elapsed;
    }

    /**
     * Returns the recorded loops
     *
     * @param   bool    $includeTotal
     *
     * @return  array
     */
    public function getLoops($includeTotal = true) {
        if (!$includeTotal) {
            return $this->loops;
        }

        $loops = $this->loops;
        $total = $this->getTotal();

        if (!array_key_exists(self::TOTAL_KEY, $loops)) {
            $loops[self::TOTAL_KEY] = $total;
        } else {
            $loops[] = $total;
        }

        return $loops;
    }

    /**
     * Returns the total time recorder
     *
     * @return float
     */
    public function getTotal() {
        return array_sum($this->loops);
    }

    /**
     * Outputs the measurement results
     *
     * @param   bool    $includeTotal
     * @param   bool    $returnString
     *
     * @return  string|null
     */
    public function printResults($includeTotal = true, $returnString = false) {
        $loops = $this->getLoops($includeTotal);
        $lines = array();

        $lines[] = '<table border="1">';
        foreach ($loops as $loop => $elapsed) {
            $lines[] = sprintf("<tr><td><strong>%s:</strong></td><td>%.3f sec</td></tr>", $loop, $elapsed);
        }
        $lines[] = "</table>";

        $output = implode(PHP_EOL, $lines);

        if ($returnString) {
            return $output;
        }

        print $output;
    }
}