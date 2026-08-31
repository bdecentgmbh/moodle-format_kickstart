<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Notice rendered above the course content.
 *
 * @package    format_kickstart
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_kickstart\output;

use renderable;
use templatable;

/**
 * Notice rendered above the course content, e.g. while an automatic template
 * application is pending.
 */
class course_notice implements renderable, templatable {
    /** @var string Notice text. */
    protected $message;

    /** @var string Bootstrap alert type suffix: warning, danger or info. */
    protected $type;

    /** @var \moodle_url|null Optional action link. */
    protected $url;

    /** @var string|null Text of the optional action link. */
    protected $urltext;

    /**
     * Constructor.
     *
     * @param string $message notice text.
     * @param string $type bootstrap alert type suffix: warning, danger or info.
     * @param \moodle_url|null $url optional action link.
     * @param string|null $urltext text of the optional action link.
     */
    public function __construct(
        string $message,
        string $type = 'warning',
        ?\moodle_url $url = null,
        ?string $urltext = null
    ) {
        $this->message = $message;
        $this->type = $type;
        $this->url = $url;
        $this->urltext = $urltext;
    }

    /**
     * Export the notice data for the mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        return [
            'message' => $this->message,
            'type' => $this->type,
            'haslink' => !empty($this->url),
            'url' => $this->url ? $this->url->out(false) : '',
            'urltext' => (string) $this->urltext,
        ];
    }
}
