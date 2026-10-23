<?php
// Run inside the API container: php tests/smoke.php. Uses local admin credentials without printing them.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$token='';
function callApi(string $method,string $path,?array $body=null,int $expected=200): array {
    global $token;
    $headers="Accept: application/json\r\nContent-Type: application/json\r\n";
    if($token!=='') $headers.="Authorization: Bearer $token\r\n";
    $options=['http'=>['method'=>$method,'header'=>$headers,'ignore_errors'=>true,'timeout'=>25]];
    if($body!==null) $options['http']['content']=json_encode($body);
    $result=file_get_contents('http://127.0.0.1:8000/api/v1'.$path,false,stream_context_create($options));
    preg_match('/\s(\d{3})\s/',$http_response_header[0]??'', $match);
    $status=(int)($match[1]??0); $data=json_decode($result?:'{}',true)??[];
    if($status!==$expected) { fwrite(STDERR,"FAIL $method $path expected $expected got $status ".($data['code']??'')." ".($data['message']??'')."\n"); exit(1); }
    echo "PASS $method $path $status\n";
    return $data;
}
callApi('GET','/health');
callApi('GET','/books',null,401);
callApi('POST','/auth/login',['username'=>'nonexistent','password'=>'invalid'],401);
$auth=callApi('POST','/auth/login',['username'=>config('library.admin_username'),'password'=>config('library.admin_password')]);
if(($auth['expires_in']??0)!==28800) throw new RuntimeException('TTL incorrecto');
$token=$auth['access_token'];
$categories=callApi('GET','/categories');
$tag=bin2hex(random_bytes(4)); $isbn='978'.str_pad((string)random_int(0,9999999999),10,'0',STR_PAD_LEFT);
$input=['title'=>'Prueba '.$tag,'author'=>'Autor de demostración','isbn'=>$isbn,'category_id'=>$categories[0]['id'],'total_copies'=>1];
$book=callApi('POST','/books',$input,201);
callApi('POST','/books',$input,409);
$member=callApi('POST','/members',['full_name'=>'Socio '.$tag,'document_number'=>'TEST-'.$tag],201);
$member2=callApi('POST','/members',['full_name'=>'Otro '.$tag,'document_number'=>'TEST2-'.$tag],201);
$loan=callApi('POST','/loans',['member_id'=>$member['id'],'items'=>[['book_id'=>$book['id']]]],201);
callApi('POST','/loans',['member_id'=>$member2['id'],'items'=>[['book_id'=>$book['id']]]],409);
callApi('DELETE','/books/'.$book['id'],null,409);
$input['title']='Título cambiado '.$tag;
callApi('PUT','/books/'.$book['id'],$input);
$from=gmdate('Y-m-d'); $report=callApi('GET',"/reports/most-borrowed?from=$from&to=$from");
$found=array_values(array_filter($report['rows'],fn($r)=>$r['book_id']===$book['id']));
if(($found[0]['book_title']??'')!=='Prueba '.$tag) throw new RuntimeException('El reporte perdió el título congelado');
$path='/loans/'.$loan['id'].'/items/'.$loan['items'][0]['id'].'/return';
callApi('POST',$path);
callApi('POST',$path,null,409);
$closed=callApi('GET','/loans/'.$loan['id']);
if($closed['status']!=='closed') throw new RuntimeException('El préstamo no cerró');
callApi('DELETE','/books/'.$book['id'],null,204);
callApi('GET','/books/'.$book['id'],null,404);
$user=callApi('POST','/users',['username'=>'test-'.$tag,'password'=>'test-only-'.$tag,'role'=>'librarian'],201);
callApi('POST','/users',['username'=>'admin-'.$tag,'password'=>'test-only-'.$tag,'role'=>'admin'],422);
$login=callApi('POST','/auth/login',['username'=>$user['username'],'password'=>'test-only-'.$tag]);
$token=$login['access_token'];
callApi('GET','/reports/most-borrowed?from='.$from.'&to='.$from,null,403);
callApi('GET','/books');
echo "SMOKE OK: login, roles, catálogo, préstamo, devolución e histórico.\n";
