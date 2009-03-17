<?php

define('_url_minuscules', true);
define('_MARQUEUR_URL', serialize(array('rubrique1' => '', 'rubrique2' => '', 'breve1' => '+', 'breve2' => '+', 'site1' => '@', 'site2' => '@', 'auteur1' => '@', 'auteur2' => '', 'mot1' => '', 'mot2' => '')));

function urls_rezo($i, $entite, $args='', $ancre='') {
	static $cache = array();

	## GENERER UNE URL
	if (is_numeric($i)) {
		// si #URL_ARTICLE est demande, pas la peine de chercher dans spip_urls.
		if ($entite === 'article') {
			return _DIR_RACINE.'a'.$i;
		}
		// si #URL_RUBRIQUE ou #URL_MOT est demandee, utiliser le static
		else if (in_array($entite, array('mot','rubrique'))) {
			if (!isset($cache[$entite])) {
				$tmp = array();
				include_spip('base/abstract_sql');
				foreach(sql_allfetsel(
					$select = array('id_objet', 'url'),
					$from = array('spip_urls'),
					$where = array("type='$entite'"),
					$groupby = array(),
					$orderby = array('date')) as $t
				) {
					$tmp[$t['id_objet']] = $t['url'];
				}
				$cache[$entite] = $tmp;
			}
		}

		if (isset($cache[$entite])
		AND isset($cache[$entite][$i])) {
			$url = $cache[$entite][$i];
			switch ($entite) {
				case 'mot':
					$url = 'themes/'.$url;
					break;
				case 'rubrique';
					$url = 'sources/'.$url;
					break;
			}
		}
		else {
			$f = charger_fonction('propres', 'urls');
			$url = $f($i, $entite, $args, $ancre);
		}

		return $url;
	}


	## DECODER UNE URL
	$mot = rawurldecode(preg_replace(',^/(backend|themes|sources)/,','', $i));

	$f = charger_fonction('propres', 'urls');
	$url = $f($mot, $entite, $args, $ancre);

	// Creer la 404 sur http://v3.rezo.net/dsds(.html)
	if ($url[1] == ''
	AND preg_match(',^.*/[^\.]+(\.html)?$,', $i))
		$url[1] = '404';

	return $url;
}

