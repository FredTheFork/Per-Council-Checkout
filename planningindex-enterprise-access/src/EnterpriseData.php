<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIE_EnterpriseData
{
    private static $all_councils = null;

    public static function all_councils(): array
    {
        if (self::$all_councils !== null) {
            return self::$all_councils;
        }

        if (function_exists('pmpc_get_all_councils')) {
            self::$all_councils = pmpc_get_all_councils();
            return self::$all_councils;
        }

        self::$all_councils = [
            'Aberdeen', 'Aberdeenshire', 'Adur', 'Allerdale', 'Amber Valley', 'Angus',
            'Antrim and Newtownabbey', 'Argyll and Bute', 'Armagh Banbridge and Craigavon',
            'Arun', 'Ashfield', 'Ashford', 'Babergh and Mid Suffolk',
            'Barking and Dagenham', 'Barnet', 'Barnsley', 'Basildon', 'Basingstoke and Deane',
            'Bassetlaw', 'Bath and North East Somerset', 'Bedford', 'Belfast', 'Bexley',
            'Birmingham', 'Blaby', 'Blackburn with Darwen', 'Blackpool', 'Blaenau Gwent',
            'Bolsover', 'Bolton', 'Boston', 'Bournemouth Christchurch and Poole',
            'Bracknell Forest', 'Bradford', 'Braintree', 'Breckland', 'Brent', 'Brentwood',
            'Bridgend', 'Brighton and Hove', 'Bristol', 'Broadland', 'Bromley',
            'Bromsgrove and Redditch', 'Broxbourne', 'Broxtowe', 'Buckinghamshire', 'Burnley', 'Bury',
            'Caerphilly', 'Calderdale', 'Cambridge', 'Cambridgeshire', 'Camden', 'Cannock Chase',
            'Canterbury', 'Cardiff', 'Carlisle', 'Carmarthenshire', 'Castle Point',
            'Central Bedfordshire', 'Ceredigion', 'Charnwood', 'Chelmsford', 'Cheltenham',
            'Cherwell', 'Cheshire East', 'Cheshire West and Chester', 'Chesterfield',
            'Chichester', 'Chorley', 'Clackmannanshire',
            'Colchester', 'Comhairle nan Eilean Siar', 'Conwy', 'Copeland',
            'Cornwall', 'Cotswold', 'Coventry', 'Crawley', 'Croydon', 'Dacorum',
            'Darlington', 'Dartford', 'Denbighshire', 'Derby', 'Derbyshire Dales',
            'Derry City and Strabane', 'Doncaster', 'Dorset', 'Dover', 'Dudley', 'Dundee',
            'Durham', 'Ealing', 'East Ayrshire', 'East Cambridgeshire', 'East Devon',
            'East Dunbartonshire', 'East Hampshire', 'East Hertfordshire', 'East Lindsey',
            'East Lothian', 'East Northamptonshire', 'East Renfrewshire', 'East Riding of Yorkshire',
            'East Staffordshire', 'East Suffolk', 'Eastbourne', 'Eastleigh', 'Edinburgh',
            'Elmbridge', 'Enfield', 'Epping Forest', 'Epsom and Ewell', 'Erewash', 'Exeter',
            'Falkirk', 'Fareham', 'Fenland', 'Fermanagh and Omagh', 'Fife', 'Flintshire',
            'Folkestone and Hythe', 'Forest of Dean', 'Fylde', 'Gateshead', 'Gedling', 'Glasgow',
            'Gloucester', 'Gosport', 'Gravesham', 'Great Yarmouth', 'Greater Manchester',
            'Greenwich', 'Guildford', 'Gwynedd', 'Hackney', 'Halton', 'Hambleton',
            'Hammersmith and Fulham', 'Harborough', 'Haringey', 'Harlow', 'Harrow', 'Harrogate',
            'Hart', 'Hartlepool', 'Hastings', 'Havant', 'Havering', 'Herefordshire', 'Hertsmere',
            'High Peak', 'Highland', 'Hillingdon', 'Hinckley and Bosworth', 'Horsham', 'Hounslow',
            'Huntingdonshire', 'Hyndburn', 'Inverclyde', 'Ipswich', 'Isle of Anglesey',
            'Isle of Wight', 'Isles of Scilly', 'Islington', 'Kensington and Chelsea',
            'Kings Lynn and West Norfolk', 'Kingston-upon-Hull', 'Kingston upon Thames',
            'Kirklees', 'Knowsley', 'Lambeth', 'Lancaster', 'Leeds', 'Leicester', 'Lewes',
            'Lewisham', 'Lichfield', 'Lincoln', 'Liverpool', 'London', 'Luton', 'Maidstone',
            'Maldon', 'Malvern Hills', 'Manchester', 'Mansfield', 'Medway', 'Melton', 'Mendip',
            'Merthyr Tydfil', 'Merton', 'Mid and East Antrim', 'Mid Devon', 'Mid Sussex',
            'Mid Ulster', 'Middlesbrough', 'Midlothian', 'Milton Keynes', 'Mole Valley',
            'Monmouthshire', 'Moray', 'Neath Port Talbot', 'New Forest', 'Newark and Sherwood',
            'Newcastle', 'Newcastle-under-Lyme', 'Newham', 'Newport', 'Newry Mourne and Down',
            'North Ayrshire', 'North Devon', 'North Down and Ards', 'North East Derbyshire',
            'North East Lincolnshire', 'North Hertfordshire', 'North Kesteven', 'North Lanarkshire',
            'North Lincolnshire', 'North Norfolk', 'North Northamptonshire', 'North Somerset',
            'North Tyneside', 'North Warwickshire', 'North West Leicestershire', 'North Yorkshire', 'Northumberland',
            'Norwich', 'Nottingham', 'Nuneaton and Bedworth', 'Oadby and Wigston', 'Oldham',
            'Orkney Islands', 'Oxford', 'Pembrokeshire', 'Pendle', 'Perth and Kinross',
            'Peterborough', 'Plymouth', 'Portsmouth', 'Powys', 'Preston', 'Reading', 'Redbridge',
            'Redcar and Cleveland', 'Reigate and Banstead', 'Renfrewshire', 'Rhondda-Cynon Taff',
            'Ribble Valley', 'Richmond', 'Richmondshire', 'Rochdale', 'Rochford', 'Rossendale',
            'Rother', 'Rotherham', 'Rugby', 'Runnymede', 'Rushcliffe', 'Rushmoor',
            'Rutland', 'Ryedale', 'Salford', 'Sandwell', 'Scottish Borders',
            'Sedgemoor', 'Sefton', 'Selby', 'Sevenoaks', 'Sheffield', 'Shetland Islands',
            'Shropshire', 'Slough', 'Solihull', 'Somerset West and Taunton', 'South Ayrshire',
            'South Cambridgeshire', 'South Derbyshire', 'South Gloucestershire', 'South Hams',
            'South Holland', 'South Kesteven', 'South Lanarkshire', 'South Norfolk',
            'South Oxfordshire', 'South Ribble', 'South Somerset', 'South Staffordshire',
            'South Tyneside', 'Southampton', 'Southend-on-Sea', 'Southwark', 'Spelthorne',
            'St Albans', 'St Helens', 'Stafford', 'Staffordshire Moorlands', 'Stevenage',
            'Stirling', 'Stockport', 'Stockton-on-Tees', 'Stoke-on-Trent', 'Stratford on Avon',
            'Stroud', 'Sunderland', 'Surrey Heath', 'Sutton', 'Swale', 'Swansea', 'Swindon',
            'Tameside', 'Tamworth', 'Tandridge', 'Teignbridge', 'Telford and Wrekin', 'Tendring',
            'Test Valley', 'Tewkesbury', 'Thanet', 'Three Rivers', 'Thurrock', 'Tonbridge and Malling',
            'Torbay', 'Torfaen', 'Torridge', 'Tower Hamlets', 'Trafford', 'Tunbridge Wells',
            'Uttlesford', 'Vale of Glamorgan', 'Vale of White Horse', 'Wakefield', 'Walsall',
            'Waltham Forest', 'Wandsworth', 'Warrington', 'Warwick', 'Watford', 'Waverley',
            'Wealden', 'Wellingborough', 'Welwyn Hatfield', 'West Berkshire', 'West Devon',
            'West Dunbartonshire', 'West Lancashire', 'West Lindsey', 'West Lothian',
            'West Northamptonshire', 'West Oxfordshire', 'West Suffolk', 'Westminster',
            'Westmorland and Furness', 'Wigan', 'Wiltshire', 'Winchester', 'Windsor and Maidenhead',
            'Wirral', 'Woking', 'Wokingham', 'Wolverhampton', 'Worcester', 'Worthing', 'Wrexham',
            'Wychavon', 'Wycombe', 'Wyre', 'Wyre Forest', 'York',
        ];
        return self::$all_councils;
    }

    public static function get_enterprise_price(): float
    {
        $level_id = intval(get_option(PIE_OPTION_LEVEL_ID, 0));
        if (!$level_id || !function_exists('pmpro_getLevel')) {
            return 0;
        }
        $level = pmpro_getLevel($level_id);
        return $level ? floatval($level->initial_payment) : 0;
    }
}
