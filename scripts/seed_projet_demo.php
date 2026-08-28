<?php
// scripts/seed_projet_demo.php — remplit un projet de démo complet
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/projet_recherche.php';

recherche_assurer_schema();
$db = get_db();

$id = (int)($argv[1] ?? 0);
if ($id <= 0) {
    $row = $db->query('SELECT id FROM recherche_dossiers ORDER BY id DESC LIMIT 1')->fetch();
    $id = (int)($row['id'] ?? 0);
}
if ($id <= 0) {
    fwrite(STDERR, "Aucun projet trouvé.\n");
    exit(1);
}

$contenu = [
    'presentation' => [
        'institution_porteuse' => 'Centre de Recherche en Informatique et Cyber-Technologie (CRICT)',
        'ancrage' => "Département Cybersécurité et résilience numérique\nAxe : Intelligence artificielle et sciences des données\nConakry, République de Guinée",
        'equipe' => [
            [
                'membre' => 'Dr. Mamadou Diallo',
                'grade' => 'Maître de recherche — Cybersécurité',
                'institution' => 'CRICT',
                'temps' => '40',
                'responsabilites' => 'Coordinateur scientifique du projet',
            ],
            [
                'membre' => 'Ing. Aissatou Bah',
                'grade' => 'Ingénieure de recherche — IA',
                'institution' => 'CRICT',
                'temps' => '50',
                'responsabilites' => 'Conception des modèles et expérimentation',
            ],
            [
                'membre' => 'M. Ibrahima Camara',
                'grade' => 'Doctorant — Systèmes d\'information',
                'institution' => 'UGANC / CRICT',
                'temps' => '60',
                'responsabilites' => 'Collecte de données et analyse',
            ],
        ],
    ],
    'contexte' => [
        'situation_actuelle' => "Les infrastructures numériques guinéennes font face à une hausse des cybermenaces (ransomware, phishing, fuites de données). Les institutions publiques disposent encore de peu d'outils locaux d'analyse prédictive et de détection d'anomalies adaptés au contexte national.",
        'justification_scientifique' => "Les approches classiques de détection basées sur des signatures sont insuffisantes face aux attaques zero-day. Une architecture d'IA souveraine, entraînée sur des données locales anonymisées, permettrait d'améliorer la détection tout en respectant la souveraineté numérique.",
        'justification_socio' => "Le renforcement de la résilience cyber des administrations et des PME contribue à la confiance numérique, à la protection des services publics et à la réduction des pertes économiques liées aux incidents de sécurité.",
    ],
    'litterature' => [
        'revue_critique' => "Les travaux récents en détection d'intrusion (IDS/IPS) mobilisent le deep learning (autoencodeurs, CNN, transformers) avec de bons résultats sur des jeux de données publics (NSL-KDD, CIC-IDS2017). Toutefois, ces modèles généralisent mal aux réseaux d'infrastructures africaines, où le trafic, les protocoles et les contraintes opérationnelles diffèrent.",
        'lacune' => "Absence d'un cadre méthodologique et d'un jeu de données de référence pour la détection d'anomalies adapté au contexte guinéen, combinant souveraineté des données et explicabilité des alertes.",
        'cadre_theorique' => "Théorie de la détection d'anomalies, apprentissage non supervisé / semi-supervisé, et cadre de la souveraineté numérique (contrôle local des données et des modèles).",
        'cadre_conceptuel' => "Architecture en couches : collecte → prétraitement → modèle IA → scoring d'alerte → tableau de bord opérationnel, avec boucle de feedback analyste.",
    ],
    'problematique' => [
        'constat' => "Les SOC locaux reçoivent un volume élevé de fausses alertes et manquent d'outils prédictifs adaptés à leur trafic réel.",
        'probleme_scientifique' => "Comment concevoir une architecture d'IA souveraine capable de détecter efficacement des anomalies réseau dans un environnement à données limitées et sensibles ?",
        'question_principale' => "Dans quelle mesure une architecture IA locale et explicable améliore-t-elle la détection d'anomalies par rapport aux approches signature classiques dans le contexte CRICT/Guinée ?",
        'questions_specifiques' => "QS1 : Quels indicateurs réseau sont les plus discriminants localement ?\nQS2 : Quel modèle (autoencodeur vs isolation forest vs transformer léger) offre le meilleur compromis précision/coût ?\nQS3 : Comment garantir l'explicabilité des alertes pour les analystes ?",
        'hypothese_generale' => "Une architecture IA souveraine, entraînée sur des traces locales anonymisées, réduit significativement les faux positifs tout en maintenant un haut taux de détection.",
        'hypotheses_specifiques' => "HS1 : Les features de flux (durée, octets, ports) suffisent pour un premier niveau de détection.\nHS2 : Un autoencodeur léger surpasse une détection par signatures sur les attaques inédites.\nHS3 : L'ajout d'explications (SHAP/LIME) améliore l'adoption opérationnelle.",
        'delimitation' => "Étude limitée au trafic réseau d'un environnement de laboratoire CRICT et de deux partenaires pilotes, sur 12 mois, hors malwares mobiles.",
    ],
    'objectifs' => [
        'objectif_general' => "Concevoir, expérimenter et évaluer une architecture d'IA souveraine pour la détection prédictive d'anomalies réseau adaptée au contexte guinéen.",
        'objectifs_specifiques' => "OS1 : Construire un jeu de données anonymisé de référence.\nOS2 : Développer et comparer trois modèles de détection.\nOS3 : Prototyper un tableau de bord explicable pour analystes.\nOS4 : Évaluer performances, coûts et acceptabilité.",
        'matrice' => [
            [
                'question' => 'QS1 — Features locales',
                'hypothese' => 'HS1 — Features de flux discriminantes',
                'objectif' => 'OS1 — Dataset de référence',
                'methode' => 'Collecte + feature engineering',
                'resultat' => 'Catalogue de features validé',
            ],
            [
                'question' => 'QS2 — Meilleur modèle',
                'hypothese' => 'HS2 — Autoencodeur supérieur',
                'objectif' => 'OS2 — Comparaison de modèles',
                'methode' => 'Benchmarks croisés',
                'resultat' => 'Modèle retenu + métriques',
            ],
            [
                'question' => 'QS3 — Explicabilité',
                'hypothese' => 'HS3 — SHAP améliore adoption',
                'objectif' => 'OS3 — Dashboard explicable',
                'methode' => 'Prototype + tests utilisateurs',
                'resultat' => 'Interface opérationnelle',
            ],
        ],
    ],
    'methode' => [
        'originalite' => "Première architecture IA de détection d'anomalies conçue, entraînée et évaluée avec des données et contraintes opérationnelles guinéennes, en privilégiant la souveraineté et l'explicabilité.",
        'contribution_theorique' => "Cadre conceptuel reliant détection d'anomalies, souveraineté numérique et explicabilité en contexte de données limitées.",
        'contribution_methodo' => "Protocole reproductible de collecte, anonymisation, benchmark et validation croisée adaptée aux infrastructures locales.",
        'contribution_pratique' => "Prototype déployable au CRICT et transférable aux partenaires publics.",
        'type_approche' => "Recherche appliquée mixte : expérimentation quantitative (métriques ML) et évaluation qualitative (entretiens analystes).",
        'site_cadre' => "Laboratoire Cybersécurité du CRICT (Conakry) et deux sites pilotes partenaires.",
        'population' => "Flux réseau anonymisés ; 8 à 12 analystes sécurité pour l'évaluation d'utilisabilité.",
        'echantillonnage' => "Échantillonnage raisonné des périodes de trafic (heures ouvrées / hors pointe) sur 6 mois de collecte.",
        'variables' => [
            [
                'variable' => 'Taux de détection (Recall)',
                'definition' => 'Proportion d\'attaques correctement détectées',
                'indicateur' => 'Recall ≥ 0,90',
                'instrument' => 'Jeux de test labellisés',
            ],
            [
                'variable' => 'Faux positifs',
                'definition' => 'Alertes incorrectes / heure',
                'indicateur' => '≤ 5 alertes/h',
                'instrument' => 'Logs SOC simulé',
            ],
            [
                'variable' => 'Explicabilité perçue',
                'definition' => 'Score d\'utilité des explications',
                'indicateur' => 'Score moyen ≥ 4/5',
                'instrument' => 'Questionnaire SUS adapté',
            ],
        ],
        'techniques_collecte' => "Capture de paquets (pcap), agrégation NetFlow, journaux firewall, questionnaires et entretiens semi-dirigés.",
        'procedure_collecte' => "1) Instrumentation labo ; 2) anonymisation ; 3) labellisation assistée ; 4) stockage sécurisé ; 5) validation éthique.",
        'methodes_analyse' => "Métriques classification (Precision, Recall, F1, AUC) ; tests statistiques de comparaison ; analyse thématique des entretiens.",
        'validite' => "Validation croisée temporelle, jeux de hold-out, revue par pairs internes, journalisation des hyperparamètres.",
        'limites' => "Volume de données limité ; généralisation à d'autres secteurs non garantie ; dépendance à la qualité du labellisation.",
        'ethique' => "Anonymisation systématique des IP et métadonnées personnelles ; consentement des sites pilotes ; pas de revente de données ; conservation limitée à 24 mois.",
    ],
    'mise_en_oeuvre' => [
        'resultats_scientifiques' => "Article soumis à une conférence régionale ; rapport technique open methodology ; dataset anonymisé documenté.",
        'livrables' => "1) Dataset CRICT-Anomaly-GN v1\n2) Modèles entraînés + code\n3) Dashboard analyste\n4) Guide d'exploitation",
        'effets_attendus' => "Réduction des faux positifs, montée en compétence des analystes, meilleure visibilité des menaces locales.",
        'impact_attendu' => "Contribution à la souveraineté numérique nationale et transfert de capacités vers les institutions partenaires.",
        'chronogramme' => [
            [
                'activite' => 'Cadrage & instrumentation',
                'responsable' => 'Dr. Diallo',
                'livrables' => 'Plan + infra labo',
                'debut' => '01/2026',
                'fin' => '03/2026',
                'indicateurs' => 'Infra opérationnelle',
            ],
            [
                'activite' => 'Collecte & dataset',
                'responsable' => 'I. Camara',
                'livrables' => 'Dataset v1',
                'debut' => '03/2026',
                'fin' => '07/2026',
                'indicateurs' => '≥ 1 Mo de flux utiles',
            ],
            [
                'activite' => 'Modélisation & benchmarks',
                'responsable' => 'A. Bah',
                'livrables' => 'Rapport modèles',
                'debut' => '06/2026',
                'fin' => '10/2026',
                'indicateurs' => 'F1 ≥ 0,85',
            ],
            [
                'activite' => 'Dashboard & évaluation',
                'responsable' => 'Équipe CRICT',
                'livrables' => 'Prototype + tests',
                'debut' => '09/2026',
                'fin' => '12/2026',
                'indicateurs' => 'SUS ≥ 70',
            ],
        ],
        'organisation' => "Comité de pilotage mensuel (CRICT) ; coordination scientifique assurée par le chef de projet.",
        'coordination' => "Réunions hebdomadaires d'équipe ; suivi Kanban ; revue trimestrielle avec partenaires.",
        'partenariats' => "Partenariats scientifiques avec UGANC et deux institutions publiques pilotes.",
        'suivi_eval' => [
            [
                'resultats' => 'Dataset disponible',
                'indicateurs' => 'Version publiée',
                'initial' => '0',
                'cible' => '1',
                'source' => 'Dépôt Git / rapport',
                'frequence' => 'Trimestrielle',
            ],
            [
                'resultats' => 'Performance modèle',
                'indicateurs' => 'F1-score',
                'initial' => 'N/A',
                'cible' => '≥ 0,85',
                'source' => 'Benchmarks',
                'frequence' => 'Mensuelle (phase 3)',
            ],
            [
                'resultats' => 'Adoption dashboard',
                'indicateurs' => 'Score SUS',
                'initial' => 'N/A',
                'cible' => '≥ 70',
                'source' => 'Enquête utilisateurs',
                'frequence' => 'Fin de projet',
            ],
        ],
        'risques' => [
            [
                'risque' => 'Données insuffisantes ou biaisées',
                'proba' => 'Moyenne',
                'impact' => 'Élevé',
                'attenuation' => 'Partenariats pilotes + génération de trafic labo',
                'responsable' => 'I. Camara',
            ],
            [
                'risque' => 'Retard d\'équipement labo',
                'proba' => 'Faible',
                'impact' => 'Moyen',
                'attenuation' => 'Commandes anticipées + cloud temporaire sécurisé',
                'responsable' => 'Dr. Diallo',
            ],
            [
                'risque' => 'Faible adoption par les analystes',
                'proba' => 'Moyenne',
                'impact' => 'Élevé',
                'attenuation' => 'Co-conception UX + formation',
                'responsable' => 'A. Bah',
            ],
        ],
    ],
    'budget_fin' => [
        'budget' => [
            [
                'activite' => 'Serveurs / stockage labo',
                'quantite' => '2',
                'cout_unitaire' => '45000000',
                'cout_total' => '90000000',
                'justification' => 'Infrastructure d\'entraînement et de stockage sécurisé',
            ],
            [
                'activite' => 'Licences / outils logiciels',
                'quantite' => '1',
                'cout_unitaire' => '15000000',
                'cout_total' => '15000000',
                'justification' => 'Outils d\'analyse et monitoring',
            ],
            [
                'activite' => 'Indemnités chercheurs (12 mois)',
                'quantite' => '3',
                'cout_unitaire' => '24000000',
                'cout_total' => '72000000',
                'justification' => 'Mobilisation de l\'équipe cœur',
            ],
            [
                'activite' => 'Missions & ateliers partenaires',
                'quantite' => '4',
                'cout_unitaire' => '5000000',
                'cout_total' => '20000000',
                'justification' => 'Collecte terrain et restitution',
            ],
            [
                'activite' => 'Publication & diffusion',
                'quantite' => '1',
                'cout_unitaire' => '8000000',
                'cout_total' => '8000000',
                'justification' => 'Frais de conférences et édition',
            ],
        ],
        'publications' => "1 article scientifique soumis ; 1 rapport technique CRICT diffusé en interne.",
        'communications' => "Présentation au séminaire CRICT ; communication à une conférence régionale en cybersécurité.",
        'vulgarisation' => "Fiche synthétique à destination des décideurs publics ; atelier de sensibilisation.",
        'valorisation_pi' => "Code et méthodologie sous licence ouverte adaptée ; dataset anonymisé documenté ; pas de cession exclusive.",
        'durabilite' => "Maintien du prototype au sein du labo CRICT ; formation de deux ingénieurs ; intégration possible dans l'offre de services du centre.",
        'references' => "1. Chandola et al. — Anomaly detection: A survey.\n2. CIC-IDS2017 — Canadian Institute for Cybersecurity.\n3. Goodfellow et al. — Deep Learning.\n4. Documents stratégiques nationaux sur la cybersécurité.",
        'annexes_notes' => "Annexe A : schéma d'architecture\nAnnexe B : questionnaire analystes\nAnnexe C : protocole d'anonymisation",
    ],
];

$titre = "Architecture d'IA souveraine pour la détection prédictive d'anomalies réseau — contexte guinéen";
$pct = recherche_calculer_pct($contenu);

$db->prepare("
    UPDATE recherche_dossiers
    SET titre = ?, contenu = ?, pct_complet = ?, statut = 'EN_COURS', modifie_le = NOW()
    WHERE id = ?
")->execute([
    $titre,
    json_encode($contenu, JSON_UNESCAPED_UNICODE),
    $pct,
    $id,
]);

$row = $db->prepare('SELECT id, code, titre, pct_complet, statut FROM recherche_dossiers WHERE id=?');
$row->execute([$id]);
$info = $row->fetch(PDO::FETCH_ASSOC);

echo "OK — Projet #$id rempli.\n";
echo "Code   : {$info['code']}\n";
echo "Titre  : {$info['titre']}\n";
echo "Statut : {$info['statut']} ({$info['pct_complet']} %)\n";
echo "URL    : /modules/projet_recherche/editer.php?id={$id}&etape=1\n";
