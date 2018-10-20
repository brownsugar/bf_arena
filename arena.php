<?php

class arena {

	const AJAX_URL = 'http://bf.nexon.com/rank/FastStart/FastStartRanking?cpage=';
	const ACF_NAME = 'arena_ranks';

	private $page = 1;
	private $total_page = 0;
	private $last_level = -1;
	private $last_process = 0;

	public $result;

	function __construct() {
		$this->result = [
				[ 'name' => '原石', 'start' => 0, 'end' => 0, 'total' => 0 ],
				[ 'name' => '銅', 'start' => 0, 'end' => 0, 'total' => 0 ],
				[ 'name' => '銀', 'start' => 0, 'end' => 0, 'total' => 0 ],
				[ 'name' => '金', 'start' => 0, 'end' => 0, 'total' => 0 ],
				[ 'name' => '白金', 'start' => 0, 'end' => 0, 'total' => 0 ],
				[ 'name' => '鑽石', 'start' => 0, 'end' => 0, 'total' => 0 ],
				[ 'name' => '達人', 'start' => 0, 'end' => 0, 'total' => 0 ],
			];
	}

	function process() {
		$data = $this->parse(self::AJAX_URL.$this->page);
		$ranks = $data['ranks'];
		$levels = $data['levels'];

		if($data['error']) {
			return false;
		}
		if(!$this->total_page) {
			$this->total_page = $data['total_page'];
		}

		foreach($levels as $i => $level) {
			$rank = intval(str_replace(',', '', $ranks[$i]));

			if($this->last_level != $level) {
				// page turns too much, subtract it
				if( $this->page > $this->last_process + 1 &&
					$this->page > 10 && $i == 0) {
					$this->page--;
					return $this->process();
				}

				$this->last_level = $level;
				$this->result[$level]['start'] = intval($rank);

				$prev_level = $level + 1;
				if($prev_level < count($this->result) && $this->result[$prev_level]['start']) {
					$endrank = $rank - 1;
					$this->result[$prev_level]['end'] = $endrank;
					$this->result[$prev_level]['total'] = $endrank - $this->result[$prev_level]['start'] + 1;
				}
			}

			// last item on the last page
			if($i == count($levels) - 1 && $this->page == $this->total_page) {
				$this->result[$level]['end'] = $rank;
				$this->result[$level]['total'] = $rank - $this->result[$level]['start'] + 1;
				return true;
			}
		}

		$this->last_process = $this->page;

		// first 10 pages process one by one, then process every 10 pages
		$this->page = $this->page + ($this->page < 10 ? 1 : 10);
		if($this->page > $this->total_page) {
			$this->page = $this->total_page;
		}

		sleep(0.2);

		return $this->process();
	}

	private function parse($url) {
		$content = file_get_contents($url);

		// match: 1위, 2위, 3위, 4, 5, ..., 999, 1,000, ...
		preg_match_all('/<th class="num">(?:<span>|.*alt=")((?:\d|,)+)(?:<\/span>|위".*)<\/th>/i', $content, $match);
		$result['ranks'] = $match[1];

		// match: 6등급, 5등급, ...
		preg_match_all('/alt="(\d+)등급"/i', $content, $match);
		$result['levels'] = $match[1];

		// match: page query of last page link
		preg_match_all('/href="\/rank\/FastStart\/FastStartRanking\?cpage=(\d+)"><img alt="마지막"/i', $content, $match);
		$result['total_page'] = $match[1] ? intval($match[1][0]) : 0;

		// no ranks found and didn't show the empty message
		$result['error'] = !$result['ranks'] && strpos($content, '랭킹결과가 없습니다.') === false;

		return $result;
	}

	function get($page_id) {
		// get data from custom field
		// https://www.advancedcustomfields.com/resources/get_field/
		$data = get_field(self::ACF_NAME, $page_id);

		return $data;
	}

	function save($page_id) {
		$data = $this->get($page_id);

		if(!is_array($data)) {
			$data = [];
		}

		$new_data = [
			'arena_rank_date' => date('Y-m-d', strtotime('-1 day'))
		];
		foreach($this->result as $i => $rank) {
			$new_data['arena_rank_'.$i] = [
				'arena_rank_start' => $rank['start'],
				'arena_rank_end' => $rank['end'],
				'arena_rank_total' => $rank['total'],
			];
		}

		$data[] = $new_data;

		// save data to custom field
		// https://www.advancedcustomfields.com/resources/update_field/
		update_field(self::ACF_NAME, $data, $page_id);
	}

	function clear($page_id) {
		// delete custom field value 
		// https://www.advancedcustomfields.com/resources/delete_field/
		delete_field(self::ACF_NAME, $page_id);
	}

}

?>
