<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json; charset=utf-8");

$config = parse_ini_file('.env');
$base = "https://aprendeinea.inea.gob.mx/plataforma";
$user = $config['MOODLE_USER'];
$pass = $config['MOODLE_PASSWORD'];;
$day = $_GET['day'] ?? null;
$month = $_GET['month'] ?? null;
$year = $_GET['year'] ?? null;

$cookie = __DIR__."/cookies.txt";
$cache = __DIR__."/usuarios.json";
$cacheTime = 3600; // 1 hora

if( !$day || !$month || !$year ){
    $fecha = new DateTime();
    $fecha->modify('-5 days');
    $day   = ltrim($fecha->format('d'), '0'); // quita el cero inicial
    $month = ltrim($fecha->format('m'), '0');
    $year = $fecha->format('Y');
}

/* ---------- BORRA CACHE CON CADA BUSQUEDA ---------- */

$filesToDelete = [$cache, __DIR__."/cookies.txt", __DIR__."/usuarios.json"];
foreach($filesToDelete as $f){
    if(file_exists($f)){
        unlink($f);
    }
}

/* ---------- USAR CACHE ---------- */

if(file_exists($cache) && (time()-filemtime($cache) < $cacheTime)){
    echo file_get_contents($cache);
    exit;
}

/* ---------- FUNCION CURL ---------- */

function curlRequest($url,$post=null){

    global $cookie;

    $ch = curl_init();

    curl_setopt_array($ch,[
        CURLOPT_URL=>$url,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_COOKIEJAR=>$cookie,
        CURLOPT_COOKIEFILE=>$cookie,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_USERAGENT=>"Mozilla/5.0",
        CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_SSL_VERIFYHOST=>false
    ]);

    if($post){
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($post));
    }

    $r=curl_exec($ch);

    return $r;
}

/* ---------- LOGIN PAGE ---------- */

$loginPage = curlRequest($base."/login/index.php");

preg_match('/name="logintoken" value="(.*?)"/',$loginPage,$m);
$token=$m[1] ?? '';

if(!$token){
    die(json_encode(["error"=>"No se pudo capturar logintoken"]));
}

/* ---------- LOGIN ---------- */

$loginResponse = curlRequest($base."/login/index.php",[
    "username"=>$user,
    "password"=>$pass,
    "logintoken"=>$token
]);

preg_match('/sesskey":"(.*?)"/',$loginResponse,$m);
$sesskey=$m[1] ?? '';

if(!$sesskey){
    die(json_encode(["error"=>"No se pudo capturar sesskey"]));
}

/* ---------- FILTRO ---------- */

$filterPost=[

"sesskey"=>$sesskey,
"_qf__user_add_filter_form"=>"1",
"mform_showmore_id_newfilter"=>"0",
"mform_isexpanded_id_newfilter"=>"1",

"profile_fld"=>"2",
"profile_op"=>"0",
"profile"=>"baja california sur",

"firstaccess_sdt[day]"=>"$day",
"firstaccess_sdt[month]"=>"$month",
"firstaccess_sdt[year]"=>"$year",
"firstaccess_sdt[enabled]"=>"1",

"addfilter"=>"Añadir filtro"

];

$filterPage=curlRequest($base."/admin/user.php",$filterPost);

/* ---------- PARSEAR USUARIOS ---------- */

$dom=new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($filterPage);

$xpath=new DOMXPath($dom);

$links=$xpath->query("//table//a[contains(@href,'/user/view.php?id=')]");

$ids=[];

foreach($links as $a){

    if(preg_match('/[?&]id=(\d+)/',$a->getAttribute("href"),$m)){
        $ids[]=$m[1];
    }

}

if(!$ids){
    die(json_encode(["error"=>"No se encontraron usuarios"]));
}

/* ---------- MULTI CURL ---------- */

function multiProfiles($ids){

    global $base,$cookie;

    $mh=curl_multi_init();
    $handles=[];

    foreach($ids as $id){

        $url=$base."/user/view.php?id=".$id;

        $ch=curl_init();

        curl_setopt_array($ch,[

        CURLOPT_URL=>$url,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_COOKIEFILE=>$cookie,
        CURLOPT_COOKIEJAR=>$cookie,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_USERAGENT=>"Mozilla/5.0",
        CURLOPT_SSL_VERIFYPEER=>false

        ]);

        curl_multi_add_handle($mh,$ch);

        $handles[$id]=$ch;

    }

    $running=null;

    do{
        curl_multi_exec($mh,$running);
        curl_multi_select($mh);
    }while($running>0);

    $results=[];

    foreach($handles as $id=>$ch){

        $results[$id]=curl_multi_getcontent($ch);

        curl_multi_remove_handle($mh,$ch);

    }

    curl_multi_close($mh);

    return $results;

}

/* ---------- SCRAPEAR PERFILES ---------- */

$profiles = multiProfiles($ids);

$usuarios=[];

foreach($profiles as $id=>$html){

    $dom2=new DOMDocument();
    libxml_use_internal_errors(true);
    $dom2->loadHTML($html);

    $xp2=new DOMXPath($dom2);

    $h1=$xp2->query("//h1")->item(0);
    $nombreCompleto=$h1 ? trim($h1->textContent):"";

    $partes=explode(" ",$nombreCompleto,2);

    $nombre=$partes[0] ?? "";
    $apellido=$partes[1] ?? "";

    $datosPerfil=[];

    $lis=$xp2->query("//li[contains(@class,'contentnode')]/dl");

    foreach($lis as $dl){

        $dt=$dl->getElementsByTagName("dt")->item(0);
        $dd=$dl->getElementsByTagName("dd")->item(0);

        if($dt && $dd){

            $datosPerfil[trim($dt->textContent)] = trim($dd->textContent);

        }

    }

    $usuarios[]=[

        "nombre"=>$nombre,
        "apellido"=>$apellido,
        "email"=>$datosPerfil['Dirección Email'] ?? "",
        "estado"=>$datosPerfil['Entidad federativa donde radicas actualmente'] ?? "",
        "municipio"=>$datosPerfil['Municipio donde radicas actualmente'] ?? "",
        "localidad"=>$datosPerfil['Localidad o Colonia donde radicas actualmente'] ?? "",
        "nacimiento"=>$datosPerfil['Fecha de  nacimiento'] ?? "",
        "curp"=>$datosPerfil['CURP (18 caracteres)'] ?? ""

    ];

}

/* ---------- RESPUESTA JSON ---------- */

$output=[

"generado"=>date("Y-m-d H:i:s"),
"total"=>count($usuarios),
"usuarios"=>$usuarios

];

$json=json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);

file_put_contents($cache,$json);

echo $json;

?>