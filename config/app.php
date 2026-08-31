<?php

declare(strict_types=1);

return [
    'name' => 'LiveCamForge',
    'version' => '1.0.1',
    'debug' => false,
    'demo_mode' => ['enabled' => false],
    'timezone' => 'Europe/Rome',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'provider' => 'demo',
    'providers' => [
        'enabled' => [],
    ],
    'provider_policies' => [
        // Conservative defaults also apply automatically to future providers.
        'default' => [
            'offline_retention' => false,
            // Used only when offline_retention=true. Zero means no automatic expiry.
            'offline_retention_days' => 0,
            'index_performer_pages' => false,
            'include_performers_in_sitemap' => false,
            'cache_images' => false,
        ],
        // Override only differences explicitly allowed by each provider's current terms.
        'chaturbate' => [],
        'bongacams' => [],
        'cam4' => [],
        'livejasmin' => [],
        'stripchat' => [
            'offline_retention' => true,
            'offline_retention_days' => 30,
            'cache_images' => false,
        ],
        'crakrevenue_mfc' => ['cache_images' => false],
        'crakrevenue_streamate' => ['cache_images' => false],
        'crakrevenue_chaturbate' => ['cache_images' => false],
        'crakrevenue_awempire' => ['cache_images' => false],
        'crakrevenue_stripchat' => ['cache_images' => false],
        'crakrevenue_imlive' => ['cache_images' => false],
        'crakrevenue_bongacash' => ['cache_images' => false],
        'demo' => [],
        'demo_alpha' => [],
        'demo_beta' => [],
    ],
    'admin' => [
        'enabled' => true,
        'session_name' => 'livecamforge_admin',
        // Set true explicitly behind TLS-terminating proxies if PHP does not see HTTPS.
        'secure_cookies' => null,
        'session_idle_timeout_seconds' => 3600,
        'login_max_attempts' => 5,
        'login_window_seconds' => 300,
        'login_lockout_seconds' => 600,
    ],
    'sync' => [
        'allow_empty' => false,
        'history_days' => 7,
    ],
    'catalog' => [
        // Normalized performer types imported and exposed publicly.
        // f: women, m: men, t: trans, c: couples.
        'performer_types' => ['f', 'm', 't', 'c'],
        'new_days' => 7,
        // automatic: provider flag when available, otherwise first local sighting.
        // provider: only the provider flag. first_seen: only the local insertion date.
        'new_strategies' => [
            'default' => 'automatic',
        ],
    ],
    'seo' => [
        // Set the final public HTTPS URL at deploy time. Empty means auto-detect.
        'base_url' => '',
        'adult_rating' => true,
        'sitemap_max_models' => 10000,
    ],
    'traffic' => [
        'landings' => [
            'live-cams' => [
                'enabled' => true,
                'index' => true,
                'minimum_results' => 8,
                'title' => [
                    'en' => 'Live Cam Performers Online Now',
                    'it' => 'Performer in Cam Live Online Ora',
                ],
                'heading' => [
                    'en' => 'Live cams online now',
                    'it' => 'Cam live online adesso',
                ],
                'description' => [
                    'en' => 'Browse live cam performers currently online across the enabled providers, with filters for categories, countries, tags and room status.',
                    'it' => 'Scopri le performer in cam attualmente online sui provider abilitati, con filtri per categorie, nazioni, tag e stato della room.',
                ],
                'eyebrow' => [
                    'en' => 'Live discovery',
                    'it' => 'Scoperta live',
                ],
                'intro' => [
                    'en' => 'Browse {result_count} performers currently available in the live catalog. {site_name} refreshes provider data regularly so you can explore active rooms from one place.',
                    'it' => 'Scopri {result_count} performer attualmente disponibili nel catalogo live. {site_name} aggiorna regolarmente i dati dei provider per permetterti di esplorare le room attive da un unico posto.',
                ],
                'body' => [
                    'en' => "## Find live cam performers in one catalog\n\n{site_name} combines the currently available profiles from the enabled cam providers into a single discovery experience. Instead of checking several platforms separately, you can browse the live catalog and open the room that interests you.\n\n## Refine the live catalog\n\nUse the available filters to narrow the results by performer type, country, age range, tags or room status. Availability can change quickly on live platforms, so a performer may leave, enter a private session or return online between catalog refreshes.",
                    'it' => "## Trova performer live in un unico catalogo\n\n{site_name} riunisce i profili attualmente disponibili dei provider cam abilitati in un'unica esperienza di scoperta. Invece di controllare più piattaforme separatamente, puoi esplorare il catalogo live e aprire la room che ti interessa.\n\n## Affina il catalogo live\n\nUsa i filtri disponibili per restringere i risultati per tipo di performer, nazione, fascia d'età, tag o stato della room. La disponibilità può cambiare rapidamente sulle piattaforme live, quindi una performer può uscire, entrare in una sessione privata o tornare online tra un aggiornamento e l'altro.",
                ],
                'faq' => [
                    [
                        'question' => [
                            'en' => 'How often is the live cam catalog updated?',
                            'it' => 'Ogni quanto viene aggiornato il catalogo delle cam live?',
                        ],
                        'answer' => [
                            'en' => 'The catalog is refreshed according to the synchronization schedule configured by the site administrator. Live room availability can still change between two refreshes.',
                            'it' => 'Il catalogo viene aggiornato secondo la pianificazione di sincronizzazione configurata dall’amministratore del sito. La disponibilità delle room può comunque cambiare tra due aggiornamenti.',
                        ],
                    ],
                    [
                        'question' => [
                            'en' => 'Why can a performer become unavailable after I open a profile?',
                            'it' => 'Perché una performer può risultare non disponibile dopo aver aperto il profilo?',
                        ],
                        'answer' => [
                            'en' => 'Live cam status changes in real time. A performer can go offline, switch room state or become temporarily unavailable after the most recent provider update.',
                            'it' => 'Lo stato delle cam cambia in tempo reale. Una performer può andare offline, cambiare stato della room o diventare temporaneamente non disponibile dopo l’ultimo aggiornamento del provider.',
                        ],
                    ],
                ],
                'filters' => ['sort' => 'popular'],
            ],
            'new-models' => [
                'enabled' => true,
                'index' => true,
                'minimum_results' => 4,
                'title' => [
                    'en' => 'New Cam Performers Online',
                    'it' => 'Nuove Performer Cam Online',
                ],
                'heading' => [
                    'en' => 'New cam performers online',
                    'it' => 'Nuove performer cam online',
                ],
                'description' => [
                    'en' => 'Discover recently identified cam performers who are currently online across the enabled providers.',
                    'it' => 'Scopri le performer cam identificate di recente e attualmente online sui provider abilitati.',
                ],
                'eyebrow' => [
                    'en' => 'Recently discovered',
                    'it' => 'Scoperte di recente',
                ],
                'intro' => [
                    'en' => 'Explore {result_count} profiles currently classified as new. {site_name} uses provider new-model signals when available and a configurable first-seen fallback when they are not.',
                    'it' => 'Esplora {result_count} profili attualmente classificati come nuovi. {site_name} usa i segnali “new” del provider quando disponibili e, in alternativa, la prima rilevazione locale configurabile.',
                ],
                'body' => [
                    'en' => "## Discover recently added cam profiles\n\nThis page highlights performers that the catalog currently identifies as new. Depending on the provider, that can mean a provider-supplied new-performer flag or a profile that {site_name} has only recently seen for the first time.\n\n## A changing selection\n\nThe list is limited to performers who are online now, so it changes throughout the day. Use the profile cards and filters to explore new additions without losing the normal live-catalog safeguards.",
                    'it' => "## Scopri i profili cam aggiunti di recente\n\nQuesta pagina mette in evidenza le performer che il catalogo identifica attualmente come nuove. A seconda del provider, può significare un flag “new” fornito dalla piattaforma oppure un profilo rilevato da {site_name} per la prima volta solo di recente.\n\n## Una selezione che cambia\n\nL’elenco è limitato alle performer online in questo momento, quindi cambia durante la giornata. Usa le card e i filtri per esplorare le nuove aggiunte mantenendo le normali protezioni del catalogo live.",
                ],
                'faq' => [
                    [
                        'question' => [
                            'en' => 'What does “new performer” mean on this page?',
                            'it' => 'Cosa significa “nuova performer” in questa pagina?',
                        ],
                        'answer' => [
                            'en' => 'It means the profile matches the configured newness strategy. {site_name} prefers provider-supplied new flags when available and can fall back to when the profile was first discovered locally.',
                            'it' => 'Significa che il profilo rispetta la strategia di novità configurata. {site_name} privilegia i flag “new” del provider quando disponibili e può usare come fallback la data della prima rilevazione locale.',
                        ],
                    ],
                    [
                        'question' => [
                            'en' => 'Does “new” mean the performer has just started camming?',
                            'it' => '“Nuova” significa che la performer ha appena iniziato a fare cam?',
                        ],
                        'answer' => [
                            'en' => 'Not necessarily. The label describes how the profile was identified by the provider or by this catalog, not the performer’s complete history on the platform.',
                            'it' => 'Non necessariamente. L’etichetta descrive come il profilo è stato identificato dal provider o da questo catalogo, non la storia completa della performer sulla piattaforma.',
                        ],
                    ],
                ],
                'filters' => ['new_only' => true, 'new_days' => 7, 'sort' => 'newest'],
            ],
            'popular-models' => [
                'enabled' => true,
                'index' => true,
                'minimum_results' => 8,
                'title' => [
                    'en' => 'Popular Live Cam Performers',
                    'it' => 'Performer Live Cam Popolari',
                ],
                'heading' => [
                    'en' => 'Popular live cam performers',
                    'it' => 'Performer live cam popolari',
                ],
                'description' => [
                    'en' => 'Explore popular cam performers currently online, ranked with the audience and popularity signals available from enabled providers.',
                    'it' => 'Esplora le performer cam popolari attualmente online, ordinate usando i segnali di pubblico e popolarità disponibili dai provider abilitati.',
                ],
                'eyebrow' => [
                    'en' => 'Popular now',
                    'it' => 'Popolari adesso',
                ],
                'intro' => [
                    'en' => 'Browse {result_count} currently online profiles ordered by the popularity data available to {site_name}. Provider signals differ, so the ranking is designed for discovery rather than as a universal leaderboard.',
                    'it' => 'Scopri {result_count} profili attualmente online ordinati in base ai dati di popolarità disponibili a {site_name}. I segnali differiscono tra provider, quindi la classifica è pensata per la scoperta e non come graduatoria universale.',
                ],
                'body' => [
                    'en' => "## See who is getting attention right now\n\nPopular sorting helps surface active rooms using the audience information supplied by providers that expose it. When a provider does not publish an equivalent metric, {site_name} applies its configured fallback so the combined catalog remains usable.\n\n## Popularity changes with the live audience\n\nLive rankings are temporary by nature. Viewer counts and room activity can change from one refresh to the next, so this page is best used as a quick way to discover busy rooms that are online now.",
                    'it' => "## Scopri chi sta attirando attenzione adesso\n\nL’ordinamento per popolarità mette in evidenza le room attive usando le informazioni sul pubblico fornite dai provider che le rendono disponibili. Quando un provider non pubblica una metrica equivalente, {site_name} applica il fallback configurato per mantenere utile il catalogo combinato.\n\n## La popolarità cambia con il pubblico live\n\nLe classifiche live sono per natura temporanee. Numero di spettatori e attività delle room possono cambiare da un aggiornamento all’altro, quindi questa pagina è pensata come modo rapido per scoprire room frequentate attualmente online.",
                ],
                'faq' => [
                    [
                        'question' => [
                            'en' => 'How are popular cam performers ranked?',
                            'it' => 'Come vengono ordinate le performer cam popolari?',
                        ],
                        'answer' => [
                            'en' => '{site_name} uses provider audience or popularity data when the integration exposes it and applies its configured fallback for sources without a comparable metric.',
                            'it' => '{site_name} usa i dati di pubblico o popolarità del provider quando l’integrazione li espone e applica il fallback configurato alle sorgenti senza una metrica comparabile.',
                        ],
                    ],
                    [
                        'question' => [
                            'en' => 'Why does the order change during the day?',
                            'it' => 'Perché l’ordine cambia durante la giornata?',
                        ],
                        'answer' => [
                            'en' => 'Popularity reflects live activity. Audience levels and online availability change continuously, so rankings can move after every synchronization.',
                            'it' => 'La popolarità riflette l’attività live. Il pubblico e la disponibilità online cambiano continuamente, quindi l’ordine può variare dopo ogni sincronizzazione.',
                        ],
                    ],
                ],
                'filters' => ['sort' => 'popular'],
            ],
            'female-cams' => [
                'enabled' => true,
                'index' => true,
                'minimum_results' => 8,
                'title' => [
                    'en' => 'Women Live on Cam',
                    'it' => 'Donne Live in Cam',
                ],
                'heading' => [
                    'en' => 'Women live on cam',
                    'it' => 'Donne live in cam',
                ],
                'description' => [
                    'en' => 'Browse women currently broadcasting live across the enabled cam providers and refine the catalog with countries, tags and room filters.',
                    'it' => 'Scopri le donne attualmente in diretta sui provider cam abilitati e affina il catalogo con nazioni, tag e filtri della room.',
                ],
                'eyebrow' => [
                    'en' => 'Women online',
                    'it' => 'Donne online',
                ],
                'intro' => [
                    'en' => 'Browse {result_count} women currently online across the enabled providers. The page stays focused on live availability while keeping the normal catalog filters available for further discovery.',
                    'it' => 'Scopri {result_count} donne attualmente online sui provider abilitati. La pagina resta focalizzata sulla disponibilità live mantenendo i normali filtri del catalogo per affinare la ricerca.',
                ],
                'body' => [
                    'en' => "## Browse women who are live now\n\nThis curated landing applies the women performer type to the shared live catalog. Profiles come from the providers enabled by the site administrator and are normalized into the same card and filtering experience.\n\n## Narrow the results without losing the category\n\nYou can continue exploring by country, age range, tags and room status. Because the source data is live, individual rooms can change state between two catalog refreshes.",
                    'it' => "## Esplora le donne live adesso\n\nQuesta landing applica il tipo performer “donne” al catalogo live condiviso. I profili arrivano dai provider abilitati dall’amministratore e vengono normalizzati nella stessa esperienza di card e filtri.\n\n## Affina i risultati mantenendo la categoria\n\nPuoi continuare a esplorare per nazione, fascia d’età, tag e stato della room. Poiché i dati sorgente sono live, le singole room possono cambiare stato tra due aggiornamenti del catalogo.",
                ],
                'faq' => [
                    [
                        'question' => [
                            'en' => 'Where do the profiles on this page come from?',
                            'it' => 'Da dove arrivano i profili di questa pagina?',
                        ],
                        'answer' => [
                            'en' => 'They come from the cam providers enabled for the public catalog and are normalized by {site_name} before being shown together.',
                            'it' => 'Arrivano dai provider cam abilitati nel catalogo pubblico e vengono normalizzati da {site_name} prima di essere mostrati insieme.',
                        ],
                    ],
                    [
                        'question' => [
                            'en' => 'Can I filter women live cams further?',
                            'it' => 'Posso filtrare ulteriormente le cam live femminili?',
                        ],
                        'answer' => [
                            'en' => 'Yes. The catalog can be refined with the filters exposed by the installation, such as country, age range, tags and room state.',
                            'it' => 'Sì. Il catalogo può essere affinato con i filtri esposti dall’installazione, ad esempio nazione, fascia d’età, tag e stato della room.',
                        ],
                    ],
                ],
                'filters' => ['gender' => 'f', 'sort' => 'popular'],
            ],
            'male-cams' => [
                'enabled' => true,
                'index' => true,
                'minimum_results' => 4,
                'title' => [
                    'en' => 'Men Live on Cam',
                    'it' => 'Uomini Live in Cam',
                ],
                'heading' => [
                    'en' => 'Men live on cam',
                    'it' => 'Uomini live in cam',
                ],
                'description' => [
                    'en' => 'Browse men currently broadcasting live across the enabled cam providers and refine the catalog with countries, tags and room filters.',
                    'it' => 'Scopri gli uomini attualmente in diretta sui provider cam abilitati e affina il catalogo con nazioni, tag e filtri della room.',
                ],
                'eyebrow' => [
                    'en' => 'Men online',
                    'it' => 'Uomini online',
                ],
                'intro' => [
                    'en' => 'Browse {result_count} men currently online across the enabled providers, with the same live-catalog filters available for a more focused search.',
                    'it' => 'Scopri {result_count} uomini attualmente online sui provider abilitati, con gli stessi filtri del catalogo live disponibili per una ricerca più mirata.',
                ],
                'body' => [
                    'en' => "## Browse men who are live now\n\nThis page keeps the shared catalog focused on male performers that are currently available from enabled providers. {site_name} normalizes provider data so profiles can be explored through one consistent interface.\n\n## Explore by the details that matter to you\n\nUse the available country, age, tag and room-status filters to narrow the selection. Room availability remains controlled by the original provider and can change at any time.",
                    'it' => "## Esplora gli uomini live adesso\n\nQuesta pagina mantiene il catalogo condiviso focalizzato sulle performer maschili attualmente disponibili dai provider abilitati. {site_name} normalizza i dati dei provider per permettere di esplorare i profili attraverso un’unica interfaccia coerente.\n\n## Esplora in base ai dettagli che ti interessano\n\nUsa i filtri disponibili per nazione, età, tag e stato della room per restringere la selezione. La disponibilità della room resta gestita dal provider originale e può cambiare in qualsiasi momento.",
                ],
                'faq' => [
                    [
                        'question' => [
                            'en' => 'Are these profiles live right now?',
                            'it' => 'Questi profili sono live in questo momento?',
                        ],
                        'answer' => [
                            'en' => 'The page is built from the latest synchronized online catalog. A room can still change state after the most recent refresh.',
                            'it' => 'La pagina viene costruita dall’ultimo catalogo online sincronizzato. Una room può comunque cambiare stato dopo l’aggiornamento più recente.',
                        ],
                    ],
                    [
                        'question' => [
                            'en' => 'Can this page include more than one cam provider?',
                            'it' => 'Questa pagina può includere più di un provider cam?',
                        ],
                        'answer' => [
                            'en' => 'Yes. When the installation uses a combined catalog, eligible profiles from all enabled sources can appear together.',
                            'it' => 'Sì. Quando l’installazione usa un catalogo combinato, i profili idonei provenienti da tutte le sorgenti abilitate possono comparire insieme.',
                        ],
                    ],
                ],
                'filters' => ['gender' => 'm', 'sort' => 'popular'],
            ],
            'trans-cams' => [
                'enabled' => true,
                'index' => true,
                'minimum_results' => 4,
                'title' => [
                    'en' => 'Trans Performers Live on Cam',
                    'it' => 'Performer Trans Live in Cam',
                ],
                'heading' => [
                    'en' => 'Trans performers live on cam',
                    'it' => 'Performer trans live in cam',
                ],
                'description' => [
                    'en' => 'Browse trans cam performers currently online across enabled providers, with live catalog filters for countries, tags and room status.',
                    'it' => 'Scopri le performer trans attualmente online sui provider abilitati, con filtri live per nazioni, tag e stato della room.',
                ],
                'eyebrow' => [
                    'en' => 'Trans performers online',
                    'it' => 'Performer trans online',
                ],
                'intro' => [
                    'en' => 'Browse {result_count} trans performers currently online across the enabled sources and use the live-catalog filters to refine the selection.',
                    'it' => 'Scopri {result_count} performer trans attualmente online sulle sorgenti abilitate e usa i filtri del catalogo live per affinare la selezione.',
                ],
                'body' => [
                    'en' => "## Discover trans performers who are online now\n\nThis landing uses the normalized trans performer type supplied by the enabled integrations. It keeps discovery focused while preserving the same multi-provider catalog experience used throughout the site.\n\n## Refine a live, changing catalog\n\nCountry, age, tag and room-status filters can help narrow the selection further. Because performer availability comes from live provider feeds, room state can change between synchronization cycles.",
                    'it' => "## Scopri le performer trans online adesso\n\nQuesta landing usa il tipo performer trans normalizzato fornito dalle integrazioni abilitate. Mantiene la scoperta focalizzata preservando la stessa esperienza multiprovider usata nel resto del sito.\n\n## Affina un catalogo live e dinamico\n\nI filtri per nazione, età, tag e stato della room possono aiutare a restringere ulteriormente la selezione. Poiché la disponibilità arriva dai feed live dei provider, lo stato della room può cambiare tra due cicli di sincronizzazione.",
                ],
                'faq' => [
                    [
                        'question' => [
                            'en' => 'How does LiveCamForge identify trans performer profiles?',
                            'it' => 'Come identifica LiveCamForge i profili delle performer trans?',
                        ],
                        'answer' => [
                            'en' => '{site_name} normalizes the performer type provided by each enabled integration into a common catalog value.',
                            'it' => '{site_name} normalizza il tipo di performer fornito da ogni integrazione abilitata in un valore comune del catalogo.',
                        ],
                    ],
                    [
                        'question' => [
                            'en' => 'Why can the number of results change?',
                            'it' => 'Perché il numero dei risultati può cambiare?',
                        ],
                        'answer' => [
                            'en' => 'Only profiles currently present in the synchronized online catalog are shown, so the count changes as performers come online or leave.',
                            'it' => 'Vengono mostrati solo i profili presenti nel catalogo online sincronizzato, quindi il numero cambia quando le performer entrano o escono dalla diretta.',
                        ],
                    ],
                ],
                'filters' => ['gender' => 't', 'sort' => 'popular'],
            ],
            'couples' => [
                'enabled' => true,
                'index' => true,
                'minimum_results' => 4,
                'title' => [
                    'en' => 'Couples Live on Cam',
                    'it' => 'Coppie Live in Cam',
                ],
                'heading' => [
                    'en' => 'Couples live on cam',
                    'it' => 'Coppie live in cam',
                ],
                'description' => [
                    'en' => 'Browse couples currently broadcasting live across enabled cam providers and refine the selection with tags, countries and room filters.',
                    'it' => 'Scopri le coppie attualmente in diretta sui provider cam abilitati e affina la selezione con tag, nazioni e filtri della room.',
                ],
                'eyebrow' => [
                    'en' => 'Couples online',
                    'it' => 'Coppie online',
                ],
                'intro' => [
                    'en' => 'Browse {result_count} couples currently online across the enabled providers. The landing keeps the catalog focused on couple profiles while preserving the normal discovery filters.',
                    'it' => 'Scopri {result_count} coppie attualmente online sui provider abilitati. La landing mantiene il catalogo focalizzato sui profili di coppia preservando i normali filtri di scoperta.',
                ],
                'body' => [
                    'en' => "## Browse couples currently live on cam\n\nThis page collects couple profiles from the enabled providers into one live catalog. Provider-specific data is normalized so you can browse the available rooms with the same cards, sorting and filters used elsewhere on the site.\n\n## Find the kind of room you want to explore\n\nUse tags, country, age and room-status filters when they are available for the profiles in the catalog. Live room status can change quickly, so the destination provider always remains the final source of availability.",
                    'it' => "## Esplora le coppie attualmente live in cam\n\nQuesta pagina raccoglie i profili di coppia dei provider abilitati in un unico catalogo live. I dati specifici dei provider vengono normalizzati per permetterti di esplorare le room disponibili con le stesse card, ordinamenti e filtri usati nel resto del sito.\n\n## Trova il tipo di room che vuoi esplorare\n\nUsa tag, nazione, età e stato della room quando queste informazioni sono disponibili per i profili nel catalogo. Lo stato live può cambiare rapidamente, quindi il provider di destinazione resta sempre la fonte finale della disponibilità.",
                ],
                'faq' => [
                    [
                        'question' => [
                            'en' => 'Are couple profiles combined from multiple providers?',
                            'it' => 'I profili di coppia possono provenire da più provider?',
                        ],
                        'answer' => [
                            'en' => 'Yes. In combined catalog mode, couple profiles from all enabled and eligible sources can be shown together.',
                            'it' => 'Sì. In modalità catalogo combinato, i profili di coppia provenienti da tutte le sorgenti abilitate e idonee possono essere mostrati insieme.',
                        ],
                    ],
                    [
                        'question' => [
                            'en' => 'Can I search couples by tag or country?',
                            'it' => 'Posso cercare le coppie per tag o nazione?',
                        ],
                        'answer' => [
                            'en' => 'Yes, when those values are available in the normalized provider data. The public filters remain available on this landing.',
                            'it' => 'Sì, quando questi valori sono disponibili nei dati normalizzati dei provider. I filtri pubblici restano disponibili in questa landing.',
                        ],
                    ],
                ],
                'filters' => ['gender' => 'c', 'sort' => 'popular'],
            ],
        ],
    ],
    'recruitment' => [
        'models' => [
            'enabled' => false,
            'index' => true,
            'eyebrow' => [
                'en' => 'Performer opportunities',
                'it' => 'Opportunità per performer',
            ],
            'seo_title' => [
                'en' => 'Become a Cam Performer | Compare Live Cam Platforms',
                'it' => 'Diventa Performer in Cam | Confronta le Piattaforme Live',
            ],
            'heading' => [
                'en' => 'Become a live cam performer',
                'it' => 'Diventa performer in live cam',
            ],
            'description' => [
                'en' => 'Explore live cam performer programs, compare supported platforms and continue to the official registration page of the service you prefer.',
                'it' => 'Scopri i programmi per performer in live cam, confronta le piattaforme supportate e prosegui sulla pagina ufficiale di registrazione del servizio che preferisci.',
            ],
            'intro' => [
                'en' => 'Compare performer programs from supported live cam platforms and choose where you want to apply. Registration, verification, rules and payments are handled directly by each provider.',
                'it' => 'Confronta i programmi per performer delle piattaforme live cam supportate e scegli dove candidarti. Registrazione, verifica, regole e pagamenti sono gestiti direttamente da ogni provider.',
            ],
            'body' => [
                'en' => "## How becoming a cam performer works\n\nEach platform has its own onboarding process, identity and age verification, content rules, payment methods and geographic availability. Use the provider cards above to compare the options configured on this site, then continue to the official registration page.\n\n## Before you apply\n\n- Read the provider terms and performer rules carefully.\n- Check identity, age and country eligibility requirements.\n- Review payment methods, payout thresholds and schedules directly with the provider.\n- Protect your privacy and decide in advance what personal information you are comfortable sharing publicly.\n\n## Choose the platform that fits you\n\nThere is no single best platform for every performer. Audience, features, rules and earning models vary. Compare the available programs and make your decision using the current information published by each provider.",
                'it' => "## Come funziona diventare performer in cam\n\nOgni piattaforma ha un proprio processo di registrazione, verifica dell'identità e dell'età, regole sui contenuti, metodi di pagamento e disponibilità geografica. Usa le schede dei provider qui sopra per confrontare le opzioni configurate su questo sito, quindi prosegui sulla pagina ufficiale di registrazione.\n\n## Prima di candidarti\n\n- Leggi con attenzione termini e regole per performer del provider.\n- Verifica i requisiti relativi a identità, età e paese di residenza.\n- Controlla direttamente sul provider metodi di pagamento, soglie e frequenza dei pagamenti.\n- Proteggi la tua privacy e decidi in anticipo quali informazioni personali sei disposto a rendere pubbliche.\n\n## Scegli la piattaforma più adatta a te\n\nNon esiste una piattaforma migliore per ogni performer. Pubblico, funzionalità, regole e modelli di guadagno possono cambiare. Confronta i programmi disponibili e basa la tua scelta sulle informazioni aggiornate pubblicate da ciascun provider.",
            ],
            'faq' => [
                [
                    'question' => [
                        'en' => 'Does this site manage performer registration?',
                        'it' => 'Questo sito gestisce la registrazione delle performer?',
                    ],
                    'answer' => [
                        'en' => 'No. Registration takes place on the selected provider website. The provider is responsible for verification, account approval, rules and payments.',
                        'it' => 'No. La registrazione avviene sul sito del provider scelto. Il provider gestisce verifica, approvazione dell’account, regole e pagamenti.',
                    ],
                ],
                [
                    'question' => [
                        'en' => 'Are earnings guaranteed?',
                        'it' => 'I guadagni sono garantiti?',
                    ],
                    'answer' => [
                        'en' => 'No. Earnings vary by platform, performer activity, audience and many other factors. Review each provider’s current terms before registering.',
                        'it' => 'No. I guadagni variano in base alla piattaforma, all’attività della performer, al pubblico e a molti altri fattori. Consulta i termini aggiornati di ogni provider prima della registrazione.',
                    ],
                ],
                [
                    'question' => [
                        'en' => 'Can I apply to more than one platform?',
                        'it' => 'Posso candidarmi a più piattaforme?',
                    ],
                    'answer' => [
                        'en' => 'That depends on the terms of each provider. Check any exclusivity or account restrictions directly in the provider documentation.',
                        'it' => 'Dipende dai termini di ciascun provider. Verifica direttamente nella documentazione del provider eventuali vincoli di esclusiva o limitazioni dell’account.',
                    ],
                ],
            ],
            // Each provider is optional. URLs must be copied from the affiliate dashboard.
            'providers' => [],
        ],
        'webmasters' => [
            'enabled' => false,
            'index' => true,
            'eyebrow' => [
                'en' => 'Webmaster resources',
                'it' => 'Risorse per webmaster',
            ],
            'seo_title' => [
                'en' => 'Live Cam Webmaster Resources | Build an Affiliate Site',
                'it' => 'Risorse per Webmaster Live Cam | Crea un Sito Affiliate',
            ],
            'heading' => [
                'en' => 'Resources for live cam webmasters',
                'it' => 'Risorse per webmaster live cam',
            ],
            'description' => [
                'en' => 'Discover resources for building a live cam affiliate website, including the software behind this site and supported affiliate programs.',
                'it' => 'Scopri risorse per creare un sito affiliate live cam, incluso il software alla base di questo sito e i programmi di affiliazione supportati.',
            ],
            'intro' => [
                'en' => 'Interested in building your own live cam affiliate site? Explore the project, documentation and provider programs collected in our webmaster resources.',
                'it' => 'Vuoi creare un tuo sito affiliate live cam? Scopri il progetto, la documentazione e i programmi dei provider raccolti nelle nostre risorse per webmaster.',
            ],
            'body' => [
                'en' => "## Build your own live cam project\n\nA live cam affiliate site combines a frequently updated performer catalog with outbound affiliate links to supported platforms. The webmaster remains responsible for choosing providers, configuring affiliate accounts, publishing useful content and operating the site.\n\n## Start with the technology\n\nThe software behind this site is designed to aggregate supported cam providers, normalize performer data and provide catalog, landing, player and conversion-tracking features from one administration area.\n\n## Explore affiliate programs\n\nProvider availability, approval requirements, commission models and terms change over time. The webmaster resource page can point you to the project documentation and to current provider signup options so you can evaluate them directly.",
                'it' => "## Crea il tuo progetto live cam\n\nUn sito affiliate live cam combina un catalogo di performer aggiornato frequentemente con link affiliati verso le piattaforme supportate. Il webmaster resta responsabile della scelta dei provider, della configurazione degli account affiliate, della pubblicazione di contenuti utili e della gestione del sito.\n\n## Parti dalla tecnologia\n\nIl software alla base di questo sito è progettato per aggregare provider cam supportati, normalizzare i dati delle performer e offrire catalogo, landing, player e tracciamento conversioni da un'unica area di amministrazione.\n\n## Esplora i programmi affiliate\n\nDisponibilità dei provider, requisiti di approvazione, modelli di commissione e termini cambiano nel tempo. La pagina di risorse per webmaster può indirizzarti alla documentazione del progetto e alle opzioni di registrazione correnti, così da valutarle direttamente.",
            ],
            'cta_label' => [
                'en' => 'Explore webmaster resources →',
                'it' => 'Esplora le risorse per webmaster →',
            ],
            // Keep the default neutral. Runtime owners can point this to their own project/resource page.
            'cta_url' => '',
            'faq' => [
                [
                    'question' => [
                        'en' => 'Do I need to use every supported provider?',
                        'it' => 'Devo usare tutti i provider supportati?',
                    ],
                    'answer' => [
                        'en' => 'No. A webmaster can enable only the providers that fit the site, affiliate accounts and target audience.',
                        'it' => 'No. Un webmaster può abilitare solo i provider adatti al sito, agli account affiliate disponibili e al proprio pubblico.',
                    ],
                ],
                [
                    'question' => [
                        'en' => 'Do affiliate programs require separate approval?',
                        'it' => 'I programmi affiliate richiedono approvazioni separate?',
                    ],
                    'answer' => [
                        'en' => 'Usually yes. Each provider or affiliate network controls its own registration, approval, terms and commission model.',
                        'it' => 'In genere sì. Ogni provider o network affiliate gestisce autonomamente registrazione, approvazione, termini e modello di commissione.',
                    ],
                ],
                [
                    'question' => [
                        'en' => 'Where can I learn how the site software works?',
                        'it' => 'Dove posso capire come funziona il software del sito?',
                    ],
                    'answer' => [
                        'en' => 'Use the webmaster resources link configured by the site owner to reach the project documentation and related resources.',
                        'it' => 'Usa il link alle risorse per webmaster configurato dal gestore del sito per raggiungere la documentazione del progetto e le risorse collegate.',
                    ],
                ],
            ],
        ],
    ],
    'geo' => [
        // auto: server GeoIP variables, then the PHP GeoIP extension.
        // cloudflare: trust CF-IPCountry and CF-Region-Code from a proxied zone.
        'source' => 'auto',
        // Used only while debug=true to test country and region filtering locally.
        'test_country' => '',
        'test_region' => '',
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'livecamforge',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'chaturbate' => [
        'wm' => '',
        // stream_only keeps only the webcam; full_embed keeps the provider room/chat experience.
        'player_mode' => 'stream_only',
        'postback' => [
            'enabled' => false,
            'validation_salt' => '',
            'require_checksum' => true,
            'track' => 'livecamforge',
        ],
        'endpoint' => 'https://chaturbate.com/api/public/affiliates/onlinerooms/',
        'page_size' => 500,
        'max_pages' => 100,
        'timeout_seconds' => 15,
    ],
    'bongacams' => [
        'campaign_id' => 0,
        'player_mode' => 'stream_only',
        'client_ip' => '',
        'detect_public_ip' => true,
        'ip_resolver_endpoint' => 'https://api4.ipify.org',
        'ip_resolver_timeout_seconds' => 5,
        'endpoint' => 'https://bngprm.com/api/v2/models-online',
        'widget_endpoint' => 'https://bngprm.com/promo.php',
        'page_size' => 500,
        'max_pages' => 20,
        'timeout_seconds' => 20,
        'player_timeout_ms' => 12000,
        'offline_fallback_values' => [
            'profile' => 'model_profile',
            'homepage' => 'homepage',
        ],
    ],
    'cam4' => [
        'affiliate_id' => 0,
        'revenue_program' => 'rs',
        'endpoint' => 'https://api.cam4pays.com/api/v1/cams/online.json',
        'page_size' => 500,
        'max_pages' => 20,
        'timeout_seconds' => 25,
        'player_timeout_ms' => 12000,
        'tune' => [
            'network_id' => 'cam4com',
            'api_key' => '',
            'endpoint' => 'https://api.hasoffers.com/Apiv3/json',
            'lookback_days' => 3,
            'page_size' => 100,
            'timeout_seconds' => 25,
        ],
    ],
    'livejasmin' => [
        'ps_id' => '',
        'player_mode' => 'stream_only',
        'access_key' => '',
        'site_id' => 'jasmin',
        'program' => 'revs',
        'campaign_id' => '',
        'sub_aff_id' => '',
        'postback' => [
            'enabled' => false,
            'secret' => '',
            'require_secret' => true,
            'track' => 'livecamforge',
            'currency' => 'USD',
            'accept_signups' => false,
            // Change these aliases only when the A.W. Empire editor uses custom parameter names.
            'parameters' => [
                'event_hash' => 'eventHash',
                'transaction_hash' => 'transactionHash',
                'sub_affiliate_id' => 'subAffiliateId',
                'commission' => 'commission',
                'base_amount' => 'baseAmount',
                'bonus_amount' => 'bonusAmount',
                'credit_amount' => 'creditAmount',
                'date' => 'date',
                'country' => 'country',
                'program_code' => 'programCode',
                'is_first_bill' => 'isFirstBill',
                'is_rebill' => 'isRebill',
                'campaign_id' => 'campaignId',
                'campaign_name' => 'campaignName',
                'site_code' => 'siteCode',
                'member_nick' => 'memberNick',
                'transaction_type' => 'transactionType',
                'static_parameter' => 'secret',
            ],
        ],
        'categories' => ['girl', 'gay', 'transgender', 'lesbian', 'couple'],
        'feed_endpoint' => 'https://atwmcd.com/api/model/feed',
        'feed_tool' => '213_1',
        'limit' => 500,
        'image_size' => '896x504',
        'image_type' => 'ex',
        'only_free_status' => true,
        'order' => 'most_popular',
        'widget_endpoint' => 'https://edwmcr.com/embed/lfcht',
        'widget_tool' => '320_1',
        'stream_only_widget_endpoint' => 'https://edwmcr.com/embed/lf',
        'stream_only_widget_tool' => '202_1',
        'widget_categories' => [
            'f' => 'girl',
            'm' => 'boy',
            't' => 'trans',
            'c' => 'couple',
        ],
        'cta_label_key' => 'udmn',
        'landing_target' => 'freechat',
        'timeout_seconds' => 20,
        'player_timeout_ms' => 12000,
    ],
    'stripchat' => [
        'api_key' => '',
        'user_id' => '',
        'endpoint' => 'https://go.whitetrafsa.com/app/models-ext/models',
        'deleted_endpoint' => 'https://go.whitetrafsa.com/app/models-ext/models/deleted',
        'player_endpoint' => 'https://creative.whitetrafsa.com/widgets/Player',
        'timeout_seconds' => 30,
        'player_timeout_ms' => 12000,
        'tracking' => [
            'campaign_id' => '',
            'source_id' => '',
            'p1' => '',
            'p2' => '',
            'p3' => '',
        ],
        'player' => [
            'autoplay' => 'all',
            'volume_control' => true,
            'fullscreen' => true,
            'quality' => 'optimal',
        ],
        'postback' => [
            'enabled' => false,
            'secret' => '',
            'require_secret' => true,
            'currency' => 'USD',
        ],
    ],
    'crakrevenue' => [
        'api_key' => '',
        'token' => '',
        'endpoint' => 'https://performersext-api.pcvdaa.com/performers-ext',
        'user_agent' => 'LiveCamForge/1.0.1 (+https://livecamforge.com)',
        // A larger page minimizes sequential requests during multi-brand sync.
        'page_size' => 100,
        'max_pages' => 10,
        'timeout_seconds' => 25,
        'player_timeout_ms' => 12000,
        'postback' => [
            'enabled' => false,
            'secret' => '',
            'require_secret' => true,
        ],
    ],
    'player' => [
        'enabled' => true,
        'load_timeout_ms' => 8000,
        'aspect_ratio_width' => 16,
        'aspect_ratio_height' => 9,
    ],
    'rooms' => [
        'block_non_public' => true,
    ],
    'media_proxy' => [
        'enabled' => true,
        'ttl_seconds' => 120,
        'timeout_seconds' => 8,
    ],
];
