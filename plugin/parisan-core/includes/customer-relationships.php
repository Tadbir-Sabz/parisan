<?php
/** Customer notes, follow-up queue and inquiry-to-booking linkage. @package ParisanCore */
defined( 'ABSPATH' ) || exit;

function parisan_core_register_customer_relationship_page() {
	add_submenu_page( 'edit.php?post_type=parisan_staff', __( 'پیگیری و یادداشت مشتریان', 'parisan-core' ), __( 'پیگیری مشتریان', 'parisan-core' ), 'edit_others_posts', 'parisan-customer-followups', 'parisan_core_render_customer_relationship_page' );
}
add_action( 'admin_menu', 'parisan_core_register_customer_relationship_page' );

function parisan_core_handle_customer_note() {
	if ( ! current_user_can( 'edit_others_posts' ) ) { wp_die( esc_html__( 'اجازه ثبت یادداشت مشتری را ندارید.', 'parisan-core' ), '', array( 'response'=>403 ) ); }
	check_admin_referer( 'parisan_customer_note', 'parisan_customer_nonce' );
	$phone = isset($_POST['customer_phone']) && is_string($_POST['customer_phone']) ? preg_replace('/[^+0-9]/','',wp_unslash($_POST['customer_phone'])) : '';
	$note = isset($_POST['note_body']) && is_string($_POST['note_body']) ? sanitize_textarea_field(wp_unslash($_POST['note_body'])) : '';
	if ( ! preg_match('/^\+?[0-9]{7,20}$/D',$phone) || '' === $note || strlen($note)>3000 ) { wp_die(esc_html__('اطلاعات یادداشت معتبر نیست.','parisan-core'),'',array('response'=>400)); }
	global $wpdb;
	$bookings=$wpdb->prefix.'parisan_bookings';
	$staff=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT staff_id FROM {$bookings} WHERE customer_phone=%s",$phone));
	$allowed=false; foreach((array)$staff as $staff_id){ if(current_user_can('edit_post',(int)$staff_id)){$allowed=true;break;} }
	if(!$allowed){wp_die(esc_html__('برای این مشتری دسترسی مشاهده ندارید.','parisan-core'),'',array('response'=>403));}
	$wpdb->insert($wpdb->prefix.'parisan_customer_notes',array('customer_phone'=>$phone,'note_body'=>$note,'created_by'=>get_current_user_id(),'created_at_utc'=>current_time('mysql',true)),array('%s','%s','%d','%s'));
	wp_safe_redirect(add_query_arg(array('post_type'=>'parisan_staff','page'=>'parisan-customer-followups','customer_phone'=>$phone,'saved'=>1),admin_url('edit.php')));exit;
}
add_action('admin_post_parisan_customer_note','parisan_core_handle_customer_note');

function parisan_core_handle_customer_followup() {
	if(!current_user_can('edit_others_posts')){wp_die(esc_html__('اجازه ثبت پیگیری را ندارید.','parisan-core'),'',array('response'=>403));}
	check_admin_referer('parisan_customer_followup','parisan_followup_nonce');
	$phone=isset($_POST['customer_phone'])&&is_string($_POST['customer_phone'])?preg_replace('/[^+0-9]/','',wp_unslash($_POST['customer_phone'])):'';
	$due=isset($_POST['due_at'])&&is_string($_POST['due_at'])?sanitize_text_field(wp_unslash($_POST['due_at'])):'';
	$task=isset($_POST['task'])&&is_string($_POST['task'])?sanitize_textarea_field(wp_unslash($_POST['task'])):'';
	$due_dt=DateTimeImmutable::createFromFormat('!Y-m-d',$due,wp_timezone());
	if(!preg_match('/^\+?[0-9]{7,20}$/D',$phone)||!$due_dt||$due_dt->format('Y-m-d')!==$due||''===$task||strlen($task)>1000){wp_die(esc_html__('اطلاعات پیگیری معتبر نیست.','parisan-core'),'',array('response'=>400));}
	global $wpdb;
	$staff=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT staff_id FROM {$wpdb->prefix}parisan_bookings WHERE customer_phone=%s",$phone));
	$allowed=false;foreach((array)$staff as $staff_id){if(current_user_can('edit_post',(int)$staff_id)){$allowed=true;break;}}
	if(!$allowed){wp_die(esc_html__('برای این مشتری دسترسی مشاهده ندارید.','parisan-core'),'',array('response'=>403));}
	$utc=(new DateTimeImmutable($due.' 12:00:00',wp_timezone()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
	$wpdb->insert($wpdb->prefix.'parisan_customer_followups',array('customer_phone'=>$phone,'task'=>$task,'due_at_utc'=>$utc,'status'=>'open','created_by'=>get_current_user_id(),'created_at_utc'=>current_time('mysql',true)),array('%s','%s','%s','%s','%d','%s'));
	wp_safe_redirect(add_query_arg(array('post_type'=>'parisan_staff','page'=>'parisan-customer-followups','customer_phone'=>$phone,'followup_saved'=>1),admin_url('edit.php')));exit;
}
add_action('admin_post_parisan_customer_followup','parisan_core_handle_customer_followup');


function parisan_core_handle_inquiry_customer_link() {
	if ( ! current_user_can( 'edit_others_posts' ) ) { wp_die( esc_html__( 'اجازه اتصال درخواست را ندارید.', 'parisan-core' ), '', array( 'response'=>403 ) ); }
	check_admin_referer( 'parisan_inquiry_customer_link', 'parisan_link_nonce' );
	$inquiry_id = isset($_POST['inquiry_id']) ? absint($_POST['inquiry_id']) : 0;
	global $wpdb;
	$inquiries = $wpdb->prefix . 'parisan_contact_inquiries';
	$inquiry = $wpdb->get_row($wpdb->prepare("SELECT id,customer_phone FROM {$inquiries} WHERE id=%d",$inquiry_id),ARRAY_A);
	if(!$inquiry){wp_die(esc_html__('درخواست تماس پیدا نشد.','parisan-core'),'',array('response'=>404));}
	$staff = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT staff_id FROM {$wpdb->prefix}parisan_bookings WHERE customer_phone=%s",$inquiry['customer_phone']));
	$allowed = false;
	foreach((array)$staff as $staff_id){if(current_user_can('edit_post',(int)$staff_id)){$allowed=true;break;}}
	if(!$allowed){wp_die(esc_html__('رزرو مرتبطی در محدوده دسترسی شما وجود ندارد؛ اتصال انجام نشد.','parisan-core'),'',array('response'=>403));}
	$wpdb->replace($wpdb->prefix.'parisan_inquiry_customer_links',array('inquiry_id'=>$inquiry_id,'customer_phone'=>$inquiry['customer_phone'],'linked_by'=>get_current_user_id(),'linked_at_utc'=>current_time('mysql',true)),array('%d','%s','%d','%s'));
	wp_safe_redirect(add_query_arg(array('post_type'=>'parisan_staff','page'=>'parisan-customer-followups','linked'=>1),admin_url('edit.php')));exit;
}
add_action('admin_post_parisan_link_inquiry_customer','parisan_core_handle_inquiry_customer_link');

function parisan_core_handle_customer_followup_complete() {
	if(!current_user_can('edit_others_posts')){wp_die(esc_html__('اجازه تغییر وضعیت پیگیری را ندارید.','parisan-core'),'',array('response'=>403));}
	$id=isset($_POST['followup_id'])?absint($_POST['followup_id']):0;
	check_admin_referer('parisan_followup_complete_'.$id,'parisan_complete_nonce');
	global $wpdb;
	$phone=$wpdb->get_var($wpdb->prepare("SELECT customer_phone FROM {$wpdb->prefix}parisan_customer_followups WHERE id=%d",$id));
	$staff=$phone?$wpdb->get_col($wpdb->prepare("SELECT DISTINCT staff_id FROM {$wpdb->prefix}parisan_bookings WHERE customer_phone=%s",$phone)):array();
	$allowed=false;foreach((array)$staff as $staff_id){if(current_user_can('edit_post',(int)$staff_id)){$allowed=true;break;}}
	if(!$allowed){wp_die(esc_html__('برای این پیگیری دسترسی ندارید.','parisan-core'),'',array('response'=>403));}
	$wpdb->update($wpdb->prefix.'parisan_customer_followups',array('status'=>'done'),array('id'=>$id),array('%s'),array('%d'));
	wp_safe_redirect(add_query_arg(array('post_type'=>'parisan_staff','page'=>'parisan-customer-followups','customer_phone'=>$phone,'completed'=>1),admin_url('edit.php')));exit;
}
add_action('admin_post_parisan_complete_customer_followup','parisan_core_handle_customer_followup_complete');

function parisan_core_render_customer_relationship_page() {
	if(!current_user_can('edit_others_posts')){wp_die(esc_html__('اجازه مشاهده پیگیری مشتریان را ندارید.','parisan-core'),'',array('response'=>403));}
	global $wpdb;
	$bookings=$wpdb->prefix.'parisan_bookings';$phone=isset($_GET['customer_phone'])&&is_string($_GET['customer_phone'])?preg_replace('/[^+0-9]/','',wp_unslash($_GET['customer_phone'])):'';
	$staff_ids=get_posts(array('post_type'=>'parisan_staff','post_status'=>array('publish','draft','private'),'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true,'perm'=>'editable'));
	$staff_ids=array_values(array_filter(array_map('absint',$staff_ids),static function($id){return current_user_can('edit_post',$id);}));
	$ids=$staff_ids?implode(',',array_map('absint',$staff_ids)):'0';
	echo '<div class="wrap"><h1>یادداشت‌ها و پیگیری مشتریان</h1><p>دسترسی هر مشتری از روی متخصصان مرتبط و قابل ویرایش برای کاربر جاری کنترل می‌شود.</p>';
	if($phone){
		$booking=$wpdb->get_row($wpdb->prepare("SELECT customer_name,customer_phone FROM {$bookings} WHERE customer_phone=%s AND staff_id IN ({$ids}) ORDER BY starts_at_utc DESC LIMIT 1",$phone),ARRAY_A);
		if(!$booking){wp_die(esc_html__('مشتری در محدوده دسترسی شما پیدا نشد.','parisan-core'),'',array('response'=>404));}
		echo '<h2>'.esc_html($booking['customer_name']).' <span dir="ltr">'.esc_html($phone).'</span></h2>';
		echo '<h3>ثبت یادداشت داخلی</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="parisan_customer_note"><input type="hidden" name="customer_phone" value="'.esc_attr($phone).'">';wp_nonce_field('parisan_customer_note','parisan_customer_nonce');echo '<textarea name="note_body" maxlength="3000" required></textarea><p><button class="button button-primary">ثبت یادداشت</button></p></form>';
		echo '<h3>ایجاد پیگیری</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="parisan_customer_followup"><input type="hidden" name="customer_phone" value="'.esc_attr($phone).'">';wp_nonce_field('parisan_customer_followup','parisan_followup_nonce');echo '<label>تاریخ پیگیری <input type="date" name="due_at" required></label><p><textarea name="task" maxlength="1000" required placeholder="مثلاً تماس برای هماهنگی مراجعه بعدی"></textarea></p><button class="button button-primary">ثبت پیگیری</button></form>';
		$notes=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}parisan_customer_notes WHERE customer_phone=%s ORDER BY created_at_utc DESC LIMIT 100",$phone),ARRAY_A);
		echo '<h3>یادداشت‌های ثبت‌شده</h3><table class="widefat striped"><thead><tr><th>تاریخ UTC</th><th>یادداشت</th><th>ثبت‌کننده</th></tr></thead><tbody>';
		foreach((array)$notes as $n){echo '<tr><td>'.esc_html($n['created_at_utc']).'</td><td>'.esc_html($n['note_body']).'</td><td>'.esc_html(get_the_author_meta('display_name',(int)$n['created_by'])).'</td></tr>';}
		if(!$notes){echo '<tr><td colspan="3">یادداشتی ثبت نشده است.</td></tr>';}echo '</tbody></table>';
		$followups=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}parisan_customer_followups WHERE customer_phone=%s ORDER BY FIELD(status,'open','done'),due_at_utc ASC LIMIT 100",$phone),ARRAY_A);
		echo '<h3>برنامه پیگیری</h3><table class="widefat striped"><thead><tr><th>موعد UTC</th><th>شرح</th><th>وضعیت</th></tr></thead><tbody>';
		foreach((array)$followups as $f){echo '<tr><td>'.esc_html($f['due_at_utc']).'</td><td>'.esc_html($f['task']).'</td><td>'.esc_html($f['status']==='done'?'انجام‌شده':'باز');if($f['status']==='open'){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="parisan_complete_customer_followup"><input type="hidden" name="followup_id" value="'.esc_attr($f['id']).'">';wp_nonce_field('parisan_followup_complete_'.(int)$f['id'],'parisan_complete_nonce');echo '<button class="button">انجام شد</button></form>';}echo '</td></tr>';}
		if(!$followups){echo '<tr><td colspan="3">پیگیری ثبت نشده است.</td></tr>';}echo '</tbody></table>';
		echo '</div>';return;
	}
	$rows=$wpdb->get_results("SELECT b.customer_phone,MAX(b.customer_name) customer_name,MAX(b.customer_email) customer_email,MAX(b.starts_at_utc) last_visit,COUNT(*) visits,COUNT(n.id) notes,COUNT(f.id) followups FROM {$bookings} b LEFT JOIN {$wpdb->prefix}parisan_customer_notes n ON n.customer_phone=b.customer_phone LEFT JOIN {$wpdb->prefix}parisan_customer_followups f ON f.customer_phone=b.customer_phone WHERE b.staff_id IN ({$ids}) AND b.customer_phone<>'' GROUP BY b.customer_phone ORDER BY last_visit DESC LIMIT 200",ARRAY_A);
	echo '<table class="widefat striped"><thead><tr><th>مشتری</th><th>تلفن</th><th>آخرین مراجعه</th><th>یادداشت / پیگیری</th><th></th></tr></thead><tbody>';
	foreach((array)$rows as $r){$url=add_query_arg(array('post_type'=>'parisan_staff','page'=>'parisan-customer-followups','customer_phone'=>$r['customer_phone']),admin_url('edit.php'));echo '<tr><td>'.esc_html($r['customer_name']).'</td><td dir="ltr">'.esc_html($r['customer_phone']).'</td><td>'.esc_html($r['last_visit']).'</td><td>'.esc_html((int)$r['notes'].' / '.(int)$r['followups']).'</td><td><a class="button" href="'.esc_url($url).'">مدیریت ارتباط</a></td></tr>';}
	if(!$rows){echo '<tr><td colspan="5">مشتری دارای رزرو در دسترس نیست.</td></tr>';}echo '</tbody></table></div>';
}
