<?php

define('_SYNDICATION_CORRECTION', false);
define('_SYNDICATION_URL_UNIQUE', true);
define('_PERIODE_SYNDICATION', 10); // 10 min
define('_PERIODE_SYNDICATION_SUSPENDUE', 60); // 1h


define('_ID_WEBMESTRES', '3:13');  // Fil, Marcimat
define('_FULLTEXT_MAX_RESULTS', 2000);
define('_POPULARITE_TABLES', 'spip_rubriques');

function rezo_post_syndication($data) {
	static $sites = array();
	$url = $data[0];
	$id_syndic = $data[1];

	if (!isset($sites[$id_syndic]))
		$sites[$id_syndic] = sql_fetsel('*', 'spip_syndic',
		'id_syndic='.sql_quote($id_syndic));

		/* statut idiot : on pourrait dire 'bof' = 'prop' */
		/* attention la syndication demande le statut 'dispo' et non 'prop'
		  (a changer dans le core) */

	$update = array(
		'id_rubrique' => $sites[$id_syndic]['id_rubrique'],
		'id_secteur' => $sites[$id_syndic]['id_secteur']
	);

	// valeur par defaut de la source
	// si publiee : 'article' (sinon '' aka depeche)
	// un article qui arrive sans descriptif est automatiquement
	// recale en depeche
	if ($sites[$id_syndic]['statut'] == 'publie'
	AND strlen($data[2]['descriptif']) > 10)
		$update['type'] = 'article';

	// detection de la langue
	// attention sur certaines sources (http://blogs.lesoir.be/colette-braeckman/feed/)
	// la langue indiquee est anglais, alors que les textes sont fr.
	$update['lang'] = $data[2]['lang'];

	// attention aux fr-FR et autres joyeusetes
	$update['lang'] = preg_replace(',^.*(fr|en|es).*$,i', '\1', $update['lang']);

	// passer au detecteur de langue
	include_spip('inc/lang_detect');
	include_spip('inc/charsets');
	list($lang, $certitude) = lang_detect(
		translitteration($data[2]['titre'] . ' ' . $data[2]['descriptif']),
		array('fr', 'en', 'es')
	);
	spip_log(sprintf("lang_detect $lang (%02d", (100*$certitude))."%)");
	if ($certitude > 0.02)
		$update['lang'] = $lang;


	// forcer fr si langue inconnue
	if (!in_array($update['lang'], array('fr', 'en', 'es')))
		$update['lang'] = 'fr';


	lang_select($update['lang']);
	$update['langue_choisie'] = 'oui';


	// Indiquer le "bon" titre
	$update['retitre'] = trim(preg_replace(',\s+,ims', ' ', $data[2]['titre']));
	// Supprimer les trucs entre crochets, genre [by fil.rezo.net] de delicious
	$update['retitre'] = trim(preg_replace(',[[].*[]],', '', $update['retitre']));

	// "titre, par auteur"
	// "titre, par auteur (source)"
	// ne pas prendre les auteurs contenant un @ (emails affiches dans le RSS)
	if (strlen($aut = trim($data[2]['lesauteurs']))
	AND !strpos($aut, '@')
	AND !preg_match('/, (par|by|por) /i', $update['retitre'])
	AND !preg_match('/ [(].*[)]$/', $update['retitre'])
	) {
		$aut = couper($aut, 60);
		$update['retitre'] = $update['retitre']
		.', '._T('forum_par_auteur', array('auteur' => $aut));
	}


	// Ajouter sous forme de tags les mots-cles associes au site source
	$tags = array();
	foreach(sql_allfetsel(array('m.descriptif AS descriptif, m.titre AS titre'),
		array('spip_mots AS m', 'spip_mots_syndic AS l'),
		array('l.id_mot=m.id_mot', 'l.id_syndic='.$id_syndic)
	) as $t)
		$tags[$t['descriptif']] = '<a rel="tag">'.$t['titre'].'</a>';

	// S'il y a un enclosure mp3, tag audio
	if ($data[2]['enclosures']
	AND preg_match(',\.mp3,', $data[2]['enclosures']))
		$tags['audio'] = '<a rel="tag">Audio</a>';

	include_spip('inc/charsets');
	if ($data[2]['tags'])
	foreach ($data[2]['tags'] as $b) {
		$key = strtolower(translitteration(trim(supprimer_tags($b))));
		$tags[$key] = $b;
	}
	if ($tags)
		$update['tags'] = join(', ', $tags);

	// Mettre a jour
	spip_log($url . ': '.join(' | ',$update), 'syndic');
	sql_updateq('spip_syndic_articles',
		$update,
		'url='.sql_quote($url).' AND id_syndic='.sql_quote($id_syndic));

	lang_select();

	// S'il y a un element publie : invalider
	if ($data[2]['statut'] == 'publie')
		ecrire_meta('derniere_modif', time());

	return $data;
}
