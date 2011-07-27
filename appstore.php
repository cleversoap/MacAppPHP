<?php
class AppStore
{
	private $curl;
	private $country;

	public function __construct($country="us")
	{
		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
		$this->country = $country;
	}

	// TODO: Add an option to get proper names for overly long titles from their details page?
	public function Search($term,$page=1,$indexById=false)
	{
		curl_setopt($this->curl, CURLOPT_USERAGENT, "iMacAppStore/1.0.1 (Macintosh; U; Intel Mac OS X 10.6.7; en) AppleWebKit/533.20.25");
		// TODO: Page settings
		curl_setopt($this->curl, CURLOPT_URL, "http://ax.search.itunes.apple.com/WebObjects/MZSearch.woa/wa/search?q=" . urlencode($term));
		$search_xml = utf8_decode(curl_exec($this->curl));

		// Parse out the individual container elements that contain the listings
		// It may seem like for some elements the container is too big but this is needed to cleanly get elements with no ratings
		preg_match_all('/role="group"(.*)<\/button>/sU',$search_xml,$matches,PREG_OFFSET_CAPTURE);
		$matches = array_pop($matches);

		//echo $search_xml;

		foreach($matches as $match)
		{
			// Todo: Figure out why there is an extra array index after the matching that only contains numbers
			if (count($match) > 1)
				$match = array_shift($match);

			// Application ID
			preg_match('/adam-id="([^"]+)"/', $match, $cur_regex);
			$result["id"] = $cur_regex[1];

			// Application Title
			preg_match('/aria-label="([^"]+)"/', $match, $cur_regex);
			$result["title"] = htmlspecialchars_decode($cur_regex[1]);

			// Genre
			preg_match('/<li class="genre">\s*([^<]+)\s*<\/li>/m', $match, $cur_regex);
			$result["category"] = $cur_regex[1];

			// Thumbnail
			preg_match('/src="([^"]+)"/', $match, $cur_regex);
			$result["icon"] = $cur_regex[1];

			// Customer Rating if it exists
			$result["rating"] = preg_match('/<li class="customer-ratings">/', $match);
			if ($result["rating"])
			{
				preg_match_all('/rating\-star"/', $match, $cur_regex);
				$result["rating"] = count($cur_regex[0]) + preg_match('/rating\-star half"/', $match) * 0.5;
			}

			// Price
			preg_match('/<span class="price">([^<]+)<\/span>/', $match, $cur_regex);
			$result["price"] = $cur_regex[1];

			// Number of customer votes if they exist
			if(preg_match('/<span class="rating-count">(\d+)/', $match, $cur_regex))
				$result["votes"] = $cur_regex[1];
			else
				$result["votes"] = 0;


			// Add the results to the list of results returned
			if ($indexById)
				$results["results"][$result["id"]] = $result;
			else
				$results["results"][] = $result;
		}

		// Pagination
		$results["current_page"] = $page;
		if (preg_match('/<span class="pagination-description">\d+\-\d+\s*\w+\s*(\d+)/', $search_xml, $cur_regex))
		{
			$results["total_results"] = $cur_regex[1];
			$results["total_pages"] = ceil($results["total_results"] / 120);
		}
		else
		{
			$results["total_pages"] = 1;
			$results["total_results"] = count($results["results"]);
		}


		return $results;
	}

	public function Details($id)
	{
		// Change to non-app store browser as this can all be viewed in a normal browser
		// TODO: See if this data can in fact be pulled from the app store's javascript calls
		curl_setopt($this->curl, CURLOPT_USERAGENT, "MacAppPHP");
		curl_setopt($this->curl, CURLOPT_URL, "http://itunes.apple.com/" . $this->country . "/app/id" . $id);
		$details_xml = curl_exec($this->curl);

		// ID (already set)
		$details["id"] = $id;

		// Title
		preg_match('/<h1>([^<]+)</', $details_xml, $cur_regex);
		$details["title"] = $cur_regex[1];

		// Author
		preg_match('/<\/h1>\s*<h2>[^\s]+\s([^<]+)</U', $details_xml, $cur_regex);
		$details["author"] = $cur_regex[1];

		// TODO: Category
		$details["category"] = "Uknown";

		// Icon
		preg_match('/alt="' . $details["title"] . '" class="artwork" src="([^"]+)"/', $details_xml, $cur_regex);
		$details["icon"] = $cur_regex[1];

		// Description
		preg_match('/<\/h4>\s*<p>(.*)<\/p>/sUm', $details_xml, $cur_regex);
		$details["description"] = preg_replace('/<br\\s*?\/??>/i', '', htmlspecialchars_decode(utf8_encode($cur_regex[1])));

		// Screenshots
		preg_match_all('/class="landscape" src="([^"]+)"/', $details_xml, $cur_regex);
		foreach($cur_regex[1] as $screenshot)
			$details["screenshots"][] = $screenshot;

		return $details;
	}

	public function BrowseCategory($catId,$page)
	{
		// TODO
		// http://itunes.apple.com/WebObjects/MZStore.woa/wa/viewGrouping?id=29534&mt=12&s=143441
	}
}
?>