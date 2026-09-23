<?php
session_start();
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/helpers.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS') exit;
$action=$_GET['action'] ?? '';
$in=input();
try {
 $pdo=db();
 // Veritabanı yeniden kurulduğunda tarayıcıdaki eski PHP oturumunda artık var olmayan bir kullanıcı ID'si kalabilir.
 // Böyle bir durumda oturumu güvenli biçimde temizleyerek foreign key hatalarını önleriz.
 if (!empty($_SESSION['user']['id'])) {
   $sessionUserId=(int)$_SESSION['user']['id'];
   $checkUser=$pdo->prepare('SELECT id,name,username,email,avatar,trust_label,xp,created_at FROM users WHERE id=? AND status="active" LIMIT 1');
   $checkUser->execute([$sessionUserId]);
   $freshSessionUser=$checkUser->fetch();
   if ($freshSessionUser) {
     $_SESSION['user']=$freshSessionUser;
   } else {
     unset($_SESSION['user']);
   }
 }
 switch($action){
  case 'health': jsonResponse(['ok'=>true,'app'=>'Wisio','time'=>date('c')]);
  case 'register':
   $name=clean($in['name']??''); $username=strtolower(clean($in['username']??'')); $email=strtolower(clean($in['email']??'')); $password=$in['password']??'';
   if(mb_strlen($name)<2 || !preg_match('/^[a-z0-9._]{3,30}$/',$username) || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<6) jsonResponse(['ok'=>false,'message'=>'Ad, 3-30 karakterlik benzersiz kullanıcı adı, geçerli e-posta ve en az 6 karakter şifre girin. Kullanıcı adında yalnızca küçük harf, rakam, nokta ve alt çizgi kullanılabilir.'],422);
   $check=$pdo->prepare('SELECT 1 FROM users WHERE username=? OR email=? LIMIT 1'); $check->execute([$username,$email]);
   if($check->fetchColumn()) jsonResponse(['ok'=>false,'message'=>'Bu kullanıcı adı veya e-posta zaten kullanılıyor.'],409);
   $s=$pdo->prepare('INSERT INTO users(name,username,email,password_hash,trust_label) VALUES(?,?,?,?,?)');
   $s->execute([$name,$username,$email,password_hash($password,PASSWORD_DEFAULT),'Yeni']);
   $id=(int)$pdo->lastInsertId();
   $u=$pdo->query("SELECT id,name,username,email,avatar,trust_label,xp,created_at FROM users WHERE id=$id")->fetch();
   $_SESSION['user']=$u; jsonResponse(['ok'=>true,'user'=>$u]);
  case 'login':
   $email=strtolower(clean($in['email']??'')); $password=$in['password']??'';
   $s=$pdo->prepare('SELECT * FROM users WHERE email=? AND status="active" LIMIT 1'); $s->execute([$email]); $u=$s->fetch();
   if(!$u || !password_verify($password,$u['password_hash'])) jsonResponse(['ok'=>false,'message'=>'E-posta veya şifre hatalı.'],401);
   $_SESSION['user']=userPublic($u); jsonResponse(['ok'=>true,'user'=>$_SESSION['user']]);
  case 'logout': session_destroy(); jsonResponse(['ok'=>true]);
  case 'me': jsonResponse(['ok'=>true,'user'=>$_SESSION['user']??null]);
  case 'bootstrap':
   $user=$_SESSION['user']??null;
   $uid=(int)($user['id']??0);
   $communities=$pdo->query('SELECT c.*, u.name owner_name, (SELECT COUNT(*) FROM community_members cm WHERE cm.community_id=c.id AND cm.status="active") member_count, COALESCE((SELECT my.status FROM community_members my WHERE my.community_id=c.id AND my.user_id=' . $uid . ' LIMIT 1),"none") membership_status, COALESCE((SELECT my.role FROM community_members my WHERE my.community_id=c.id AND my.user_id=' . $uid . ' LIMIT 1),"none") my_role, EXISTS(SELECT 1 FROM community_members my WHERE my.community_id=c.id AND my.user_id=' . $uid . ' AND my.status="active") is_member FROM communities c JOIN users u ON u.id=c.owner_id WHERE c.status="active" ORDER BY c.id DESC')->fetchAll();
   $questions=$pdo->query('SELECT q.*,u.name asker_name,c.name community_name,t.name topic_name,(SELECT COUNT(*) FROM answers a WHERE a.question_id=q.id) answer_count FROM questions q JOIN users u ON u.id=q.asker_id JOIN communities c ON c.id=q.community_id LEFT JOIN topics t ON t.id=q.topic_id ORDER BY q.created_at DESC LIMIT 30')->fetchAll();
   $cards=$pdo->query('SELECT k.*,c.name community_name,u.name owner_name FROM knowledge_cards k JOIN communities c ON c.id=k.community_id JOIN users u ON u.id=k.owner_id WHERE k.status="active" ORDER BY k.updated_at DESC LIMIT 20')->fetchAll();
   $experts=$pdo->query('SELECT u.id,u.name,u.avatar,u.trust_label,u.xp,GROUP_CONCAT(t.name SEPARATOR ", ") expertise FROM users u LEFT JOIN user_expertise ue ON ue.user_id=u.id LEFT JOIN topics t ON t.id=ue.topic_id GROUP BY u.id ORDER BY u.xp DESC LIMIT 12')->fetchAll();
   $topics=$pdo->query('SELECT * FROM topics ORDER BY name')->fetchAll();
   $announcements=$uid?$pdo->query('SELECT a.*,c.name community_name,u.name author_name FROM announcements a JOIN communities c ON c.id=a.community_id JOIN users u ON u.id=a.author_id JOIN community_members cm ON cm.community_id=a.community_id AND cm.user_id=' . $uid . ' AND cm.status="active" WHERE a.status="published" ORDER BY a.is_pinned DESC,a.created_at DESC LIMIT 20')->fetchAll():[];
   $stats=['questions'=>(int)$pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn(),'answers'=>(int)$pdo->query('SELECT COUNT(*) FROM answers')->fetchColumn(),'cards'=>(int)$pdo->query('SELECT COUNT(*) FROM knowledge_cards')->fetchColumn(),'members'=>(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()];
   jsonResponse(compact('user','communities','questions','cards','experts','topics','announcements','stats'));
  case 'create_community':
   $u=requireAuth(); $name=clean($in['name']??''); $type=clean($in['type']??'Topluluk'); $access=$in['access_type']??'approval';
   if(!in_array($access,['open','password','approval'],true)) $access='approval';
   $joinPassword=(string)($in['join_password']??'');
   if(mb_strlen($name)<3) jsonResponse(['ok'=>false,'message'=>'Topluluk adı en az 3 karakter olmalı.'],422);
   if($access==='password' && strlen($joinPassword)<4) jsonResponse(['ok'=>false,'message'=>'Topluluk şifresi en az 4 karakter olmalı.'],422);
   $nameCheck=$pdo->prepare('SELECT 1 FROM communities WHERE LOWER(name)=LOWER(?) LIMIT 1'); $nameCheck->execute([$name]);
   if($nameCheck->fetchColumn()) jsonResponse(['ok'=>false,'message'=>'Bu topluluk adı zaten kullanılıyor. Farklı bir ad seçin.'],409);
   $visibility=$access==='open'?'open':'approved';
   $hash=$access==='password'?password_hash($joinPassword,PASSWORD_DEFAULT):null;
   $pdo->beginTransaction();
   $s=$pdo->prepare('INSERT INTO communities(owner_id,name,type,description,visibility,join_method,join_password_hash) VALUES(?,?,?,?,?,?,?)');
   $s->execute([$u['id'],$name,$type,clean($in['description']??''),$visibility,$access,$hash]);
   $cid=(int)$pdo->lastInsertId();
   $pdo->prepare('INSERT INTO community_members(community_id,user_id,role,status) VALUES(?,?,"admin","active")')->execute([$cid,$u['id']]);
   $pdo->commit(); jsonResponse(['ok'=>true,'message'=>'Topluluk oluşturuldu.']);
  case 'join_community':
   $u=requireAuth(); $cid=(int)($in['community_id']??0);
   $community=$pdo->prepare('SELECT id,visibility,join_method,join_password_hash FROM communities WHERE id=? AND status="active"');
   $community->execute([$cid]); $community=$community->fetch();
   if(!$community) jsonResponse(['ok'=>false,'message'=>'Topluluk bulunamadı veya artık aktif değil. Sayfayı yenileyin.'],404);
   if($community['join_method']==='password'){
    $given=(string)($in['join_password']??'');
    if(!$community['join_password_hash'] || !password_verify($given,$community['join_password_hash'])) jsonResponse(['ok'=>false,'message'=>'Topluluk şifresi yanlış.'],403);
   }
   $newStatus=in_array($community['join_method'],['open','password'],true)?'active':'pending';
   $s=$pdo->prepare('INSERT INTO community_members(community_id,user_id,role,status) VALUES(?,? ,"member",?) ON DUPLICATE KEY UPDATE status=VALUES(status),role=IF(role IN ("admin","moderator","knowledge_manager"),role,"member")'); $s->execute([$cid,$u['id'],$newStatus]);
   jsonResponse(['ok'=>true,'message'=>$newStatus==='active'?'Topluluğa katıldınız.':'Katılım isteğiniz yönetici onayına gönderildi.','status'=>$newStatus]);
  case 'update_community_access':
   $u=requireAuth(); $cid=(int)($in['community_id']??0); $access=$in['access_type']??'approval';
   if(!in_array($access,['open','password','approval'],true)) jsonResponse(['ok'=>false,'message'=>'Geçersiz katılım yöntemi.'],422);
   $r=$pdo->prepare('SELECT role FROM community_members WHERE community_id=? AND user_id=? AND status="active"'); $r->execute([$cid,$u['id']]);
   if($r->fetchColumn()!=='admin') jsonResponse(['ok'=>false,'message'=>'Bu ayarı yalnızca topluluk yöneticisi değiştirebilir.'],403);
   $joinPassword=(string)($in['join_password']??'');
   if($access==='password' && strlen($joinPassword)<4) jsonResponse(['ok'=>false,'message'=>'Topluluk şifresi en az 4 karakter olmalı.'],422);
   $hash=$access==='password'?password_hash($joinPassword,PASSWORD_DEFAULT):null;
   $visibility=$access==='open'?'open':'approved';
   $pdo->prepare('UPDATE communities SET visibility=?,join_method=?,join_password_hash=? WHERE id=?')->execute([$visibility,$access,$hash,$cid]);
   jsonResponse(['ok'=>true,'message'=>'Topluluk katılım ayarı güncellendi.']);
  case 'community':
   $u=requireAuth(); $cid=(int)($_GET['id']??0);
   $m=$pdo->prepare('SELECT cm.role,cm.status,c.* FROM community_members cm JOIN communities c ON c.id=cm.community_id WHERE cm.community_id=? AND cm.user_id=? LIMIT 1'); $m->execute([$cid,$u['id']]); $membership=$m->fetch();
   if(!$membership || $membership['status']!=='active') jsonResponse(['ok'=>false,'message'=>'Bu topluluğu görüntülemek için aktif üye olmalısınız.'],403);
   $members=$pdo->prepare('SELECT u.id,u.name,u.username,u.trust_label,cm.role,cm.status,cm.joined_at FROM community_members cm JOIN users u ON u.id=cm.user_id WHERE cm.community_id=? ORDER BY FIELD(cm.status,"pending","active","muted","banned"),FIELD(cm.role,"admin","moderator","knowledge_manager","member"),u.name'); $members->execute([$cid]);
   $anns=$pdo->prepare('SELECT a.*,u.name author_name FROM announcements a JOIN users u ON u.id=a.author_id WHERE a.community_id=? AND a.status="published" ORDER BY a.is_pinned DESC,a.created_at DESC'); $anns->execute([$cid]);
   jsonResponse(['ok'=>true,'community'=>$membership,'members'=>$members->fetchAll(),'announcements'=>$anns->fetchAll()]);
  case 'create_announcement':
   $u=requireAuth(); $cid=(int)($in['community_id']??0); $title=clean($in['title']??''); $content=clean($in['content']??'');
   $r=$pdo->prepare('SELECT role FROM community_members WHERE community_id=? AND user_id=? AND status="active"'); $r->execute([$cid,$u['id']]); $role=$r->fetchColumn();
   if(!in_array($role,['admin','moderator'],true)) jsonResponse(['ok'=>false,'message'=>'Yalnızca yönetici veya moderatör duyuru yayınlayabilir.'],403);
   if(mb_strlen($title)<3 || mb_strlen($content)<5) jsonResponse(['ok'=>false,'message'=>'Duyuru başlığı ve içeriğini doldurun.'],422);
   $pdo->prepare('INSERT INTO announcements(community_id,author_id,title,content,is_pinned,status) VALUES(?,?,?,?,?,"published")')->execute([$cid,$u['id'],$title,$content,!empty($in['is_pinned'])?1:0]);
   jsonResponse(['ok'=>true,'message'=>'Duyuru yayınlandı.']);
  case 'manage_member':
   $u=requireAuth(); $cid=(int)($in['community_id']??0); $target=(int)($in['user_id']??0); $operation=$in['operation']??'';
   $r=$pdo->prepare('SELECT role FROM community_members WHERE community_id=? AND user_id=? AND status="active"'); $r->execute([$cid,$u['id']]); $myRole=$r->fetchColumn();
   if($myRole!=='admin') jsonResponse(['ok'=>false,'message'=>'Bu işlem yalnızca topluluk yöneticisine açıktır.'],403);
   $owner=$pdo->prepare('SELECT owner_id FROM communities WHERE id=?'); $owner->execute([$cid]); $ownerId=(int)$owner->fetchColumn();
   if($target===$ownerId) jsonResponse(['ok'=>false,'message'=>'Topluluk sahibinin rolü değiştirilemez veya üyeliği kaldırılamaz.'],403);
   if($operation==='approve') $pdo->prepare('UPDATE community_members SET status="active" WHERE community_id=? AND user_id=?')->execute([$cid,$target]);
   elseif($operation==='reject') $pdo->prepare('DELETE FROM community_members WHERE community_id=? AND user_id=? AND status="pending"')->execute([$cid,$target]);
   elseif($operation==='remove') $pdo->prepare('DELETE FROM community_members WHERE community_id=? AND user_id=? AND role<>"admin"')->execute([$cid,$target]);
   elseif($operation==='role') { $role=$in['role']??'member'; if(!in_array($role,['admin','moderator','knowledge_manager','member'],true)) $role='member'; $pdo->prepare('UPDATE community_members SET role=? WHERE community_id=? AND user_id=? AND status="active"')->execute([$role,$cid,$target]); }
   else jsonResponse(['ok'=>false,'message'=>'Geçersiz yönetim işlemi.'],422);
   jsonResponse(['ok'=>true,'message'=>'Üye bilgisi güncellendi.']);
  case 'add_expertise':
   $u=requireAuth(); $topic=(int)($in['topic_id']??0);
   $pdo->prepare('INSERT INTO user_expertise(user_id,topic_id,source,confidence_score,verification_status,availability) VALUES(?,?,"self",60,"pending","available") ON DUPLICATE KEY UPDATE availability="available"')->execute([$u['id'],$topic]);
   jsonResponse(['ok'=>true,'message'=>'Bilgi alanı eklendi.']);
  case 'ask':
   $u=requireAuth(); $cid=(int)($in['community_id']??0); $title=clean($in['title']??'');
   $description=clean($in['description']??'');
   if(!$cid || mb_strlen($title)<12) jsonResponse(['ok'=>false,'message'=>'Topluluk seçin ve en az 12 karakterlik net bir soru yazın.'],422);
   if(mb_strlen($title)>255 || mb_strlen($description)>3000) jsonResponse(['ok'=>false,'message'=>'Soru başlığı veya açıklama izin verilen uzunluğu aşıyor.'],422);
   $member=$pdo->prepare('SELECT 1 FROM community_members WHERE community_id=? AND user_id=? AND status="active"');
   $member->execute([$cid,$u['id']]);
   if(!$member->fetchColumn()) jsonResponse(['ok'=>false,'message'=>'Soru göndermek için önce bu topluluğa katılmalısınız.'],403);
   $topic=(int)($in['topic_id']??0) ?: null;
   $visibility=in_array(($in['visibility']??'community'),['public','community','role','private'],true)?$in['visibility']:'community';
   $urgency=in_array(($in['urgency']??'normal'),['low','normal','high'],true)?$in['urgency']:'normal';
   $answerType=in_array(($in['expected_answer_type']??'any'),['any','short','steps','experience','person','document'],true)?$in['expected_answer_type']:'any';
   $targetCount=max(1,min(5,(int)($in['target_count']??3)));
   $fallback=!empty($in['allow_public_fallback'])?1:0;
   $s=$pdo->prepare('INSERT INTO questions(community_id,asker_id,title,description,topic_id,visibility,anonymity,status,urgency,expected_answer_type,target_count,allow_public_fallback) VALUES(?,?,?,?,?,?,?,"open",?,?,?,?)');
   $s->execute([$cid,$u['id'],$title,$description,$topic,$visibility,!empty($in['anonymous'])?1:0,$urgency,$answerType,$targetCount,$fallback]);
   $qid=(int)$pdo->lastInsertId();
   if($topic){
     $m=$pdo->prepare('SELECT ue.user_id, ue.confidence_score FROM user_expertise ue JOIN community_members cm ON cm.user_id=ue.user_id AND cm.community_id=? AND cm.status="active" WHERE ue.topic_id=? AND ue.availability="available" AND ue.user_id<>? ORDER BY ue.confidence_score DESC LIMIT ?');
     $m->bindValue(1,$cid,PDO::PARAM_INT); $m->bindValue(2,$topic,PDO::PARAM_INT); $m->bindValue(3,$u['id'],PDO::PARAM_INT); $m->bindValue(4,$targetCount,PDO::PARAM_INT); $m->execute();
     foreach($m->fetchAll() as $x) $pdo->prepare('INSERT IGNORE INTO question_targets(question_id,user_id,match_score,notification_status,response_status) VALUES(?,?,?,"sent","pending")')->execute([$qid,$x['user_id'],$x['confidence_score']]);
   }
   jsonResponse(['ok'=>true,'message'=>'Sorunuz uygun kişilere yönlendirildi.','question_id'=>$qid]);
  case 'question':
   $qid=(int)($_GET['id']??0);
   $s=$pdo->prepare('SELECT q.*,u.name asker_name,c.name community_name,t.name topic_name FROM questions q JOIN users u ON u.id=q.asker_id JOIN communities c ON c.id=q.community_id LEFT JOIN topics t ON t.id=q.topic_id WHERE q.id=?'); $s->execute([$qid]); $q=$s->fetch();
   if(!$q) jsonResponse(['ok'=>false,'message'=>'Soru bulunamadı.'],404);
   $a=$pdo->prepare('SELECT a.*,u.name user_name,u.trust_label,(SELECT COUNT(*) FROM answer_verifications av WHERE av.answer_id=a.id AND av.verification_type="helpful") helpful_count FROM answers a JOIN users u ON u.id=a.user_id WHERE a.question_id=? ORDER BY a.created_at'); $a->execute([$qid]);
   $targets=$pdo->prepare('SELECT u.name,u.trust_label,qt.match_score FROM question_targets qt JOIN users u ON u.id=qt.user_id WHERE qt.question_id=? ORDER BY qt.match_score DESC'); $targets->execute([$qid]);
   jsonResponse(['ok'=>true,'question'=>$q,'answers'=>$a->fetchAll(),'targets'=>$targets->fetchAll()]);
  case 'answer':
   $u=requireAuth(); $qid=(int)($in['question_id']??0); $content=clean($in['content']??'');
   if(mb_strlen($content)<3) jsonResponse(['ok'=>false,'message'=>'Cevap çok kısa.'],422);
   $q=$pdo->prepare('SELECT asker_id,status FROM questions WHERE id=?'); $q->execute([$qid]); $question=$q->fetch();
   if(!$question) jsonResponse(['ok'=>false,'message'=>'Soru bulunamadı.'],404);
   if((int)$question['asker_id']===(int)$u['id']) jsonResponse(['ok'=>false,'message'=>'Kendi sorduğunuz soruya cevap veremezsiniz. Sorunuza ayrıntı eklemek için açıklamayı kullanın.'],403);
   if($question['status']==='closed') jsonResponse(['ok'=>false,'message'=>'Bu soru cevaplara kapatılmış.'],409);
   $pdo->prepare('INSERT INTO answers(question_id,user_id,content,answer_type,status) VALUES(?,?,?,"text","published")')->execute([$qid,$u['id'],$content]);
   $pdo->prepare('UPDATE users SET xp=xp+5 WHERE id=?')->execute([$u['id']]);
   $pdo->prepare('UPDATE questions SET status="answered" WHERE id=? AND status="open"')->execute([$qid]);
   jsonResponse(['ok'=>true,'message'=>'Cevabınız yayınlandı.']);
  case 'verify':
   $u=requireAuth(); $aid=(int)($in['answer_id']??0);
   $pdo->prepare('INSERT IGNORE INTO answer_verifications(answer_id,verifier_id,verification_type,comment) VALUES(?,?,"helpful","")')->execute([$aid,$u['id']]);
   $pdo->prepare('UPDATE users u JOIN answers a ON a.user_id=u.id SET u.xp=u.xp+10 WHERE a.id=?')->execute([$aid]);
   jsonResponse(['ok'=>true,'message'=>'Cevap faydalı olarak işaretlendi.']);
  case 'create_card':
   $u=requireAuth(); $qid=(int)($in['question_id']??0);
   $s=$pdo->prepare('SELECT q.*,a.content answer FROM questions q JOIN answers a ON a.question_id=q.id WHERE q.id=? ORDER BY a.id DESC LIMIT 1'); $s->execute([$qid]); $x=$s->fetch();
   if(!$x) jsonResponse(['ok'=>false,'message'=>'Bilgi kartına dönüşecek cevap bulunamadı.'],422);
   $pdo->prepare('INSERT INTO knowledge_cards(community_id,source_question_id,title,summary,content,topic_id,visibility,owner_id,valid_until,status) VALUES(?,?,?,?,?,?,?,?,DATE_ADD(CURDATE(),INTERVAL 6 MONTH),"active")')->execute([$x['community_id'],$qid,$x['title'],mb_substr($x['answer'],0,160),$x['answer'],$x['topic_id'],$x['visibility'],$u['id']]);
   jsonResponse(['ok'=>true,'message'=>'Cevap bilgi kartına dönüştürüldü.']);
  case 'search':
   $term='%'.clean($_GET['q']??'').'%';
   $q=$pdo->prepare('SELECT id,title,"question" kind FROM questions WHERE title LIKE ? UNION ALL SELECT id,title,"card" kind FROM knowledge_cards WHERE title LIKE ? LIMIT 20'); $q->execute([$term,$term]);
   jsonResponse(['ok'=>true,'results'=>$q->fetchAll()]);
  case 'admin_stats':
   requireAuth();
   $data=['open_questions'=>(int)$pdo->query('SELECT COUNT(*) FROM questions WHERE status="open"')->fetchColumn(),'unanswered'=>(int)$pdo->query('SELECT COUNT(*) FROM questions q WHERE NOT EXISTS(SELECT 1 FROM answers a WHERE a.question_id=q.id)')->fetchColumn(),'verified'=>(int)$pdo->query('SELECT COUNT(DISTINCT answer_id) FROM answer_verifications')->fetchColumn(),'expired_cards'=>(int)$pdo->query('SELECT COUNT(*) FROM knowledge_cards WHERE valid_until<CURDATE()')->fetchColumn()];
   jsonResponse(['ok'=>true,'data'=>$data]);
  default: jsonResponse(['ok'=>false,'message'=>'Geçersiz işlem.'],404);
 }
} catch(PDOException $e){
 if(str_contains($e->getMessage(),'Duplicate entry')) jsonResponse(['ok'=>false,'message'=>'Bu kayıt zaten mevcut.'],409);
 if(str_contains($e->getMessage(),'1452') || str_contains($e->getMessage(),'foreign key constraint fails')) {
   unset($_SESSION['user']);
   jsonResponse(['ok'=>false,'message'=>'Oturum bilgisi veritabanıyla eşleşmiyor. Veritabanı yeniden kurulmuş olabilir. Sayfayı yenileyip tekrar giriş yapın.'],409);
 }
 $env=loadEnv(dirname(__DIR__).'/.env');
 $detail=(($env['APP_ENV']??'local')==='local')?' Ayrıntı: '.$e->getMessage():'';
 jsonResponse(['ok'=>false,'message'=>'Veritabanı işlemi tamamlanamadı. migration.sql dosyasını çalıştırın.'.$detail],500);
} catch(Throwable $e){ jsonResponse(['ok'=>false,'message'=>'Sunucu hatası: '.$e->getMessage()],500); }
