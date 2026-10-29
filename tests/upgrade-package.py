"""Exercise ZIP replacement with real, isolated WordPress/MySQL; never reads site credentials."""
from pathlib import Path
import argparse
import json
import os
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[1]


def execute(args, cwd=None):
    result = subprocess.run(args, cwd=cwd, capture_output=True, text=True, encoding='utf-8', errors='replace')
    if result.returncode:
        raise RuntimeError(result.stderr[-5000:] or result.stdout[-5000:])
    return result.stdout


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--mysqld', required=True, help='Path to a local mysqld binary; a separate temporary data directory is initialized')
    parser.add_argument('--package', required=True, type=Path, help='New release ZIP')
    parser.add_argument('--wordpress-zip', type=Path, help='Optional cached official WordPress ZIP')
    parser.add_argument('--old-ref', default='v1.5.0', help='Local historical Git ref to install first')
    parser.add_argument('--browser', action='store_true', help='Exercise real WordPress settings, media selection and both public forms in Edge')
    args = parser.parse_args()
    package = args.package.resolve(strict=True)
    workspace = Path(tempfile.mkdtemp(prefix='udc-wordpress-upgrade-')).resolve()
    print('Isolated workspace:', workspace, flush=True)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    data = workspace / 'mysql-data'
    data.mkdir()
    initialize = [args.mysqld, '--no-defaults', '--initialize-insecure', '--datadir=' + str(data), '--console']
    execute(initialize)
    mysql_log = (workspace / 'mysql.log').open('wb')
    server = subprocess.Popen([args.mysqld, '--no-defaults', '--datadir=' + str(data), '--bind-address=127.0.0.1', '--port=' + str(port), '--mysqlx=OFF', '--console'], stdout=mysql_log, stderr=subprocess.STDOUT, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
    try:
        for _ in range(100):
            if server.poll() is not None:
                raise RuntimeError('Temporary MySQL stopped: ' + (workspace / 'mysql.log').read_text(errors='replace')[-3000:])
            try:
                connection = socket.create_connection(('127.0.0.1', port), timeout=.2)
                connection.close()
                break
            except OSError:
                time.sleep(.2)
        else:
            raise RuntimeError('Temporary MySQL did not become ready')
        print('Temporary MySQL ready on loopback.', flush=True)
        wordpress_zip = workspace / 'wordpress.zip'
        if args.wordpress_zip:
            shutil.copyfile(args.wordpress_zip, wordpress_zip)
        else:
            with urllib.request.urlopen('https://wordpress.org/latest.zip', timeout=60) as response:
                wordpress_zip.write_bytes(response.read())
        with zipfile.ZipFile(wordpress_zip) as archive:
            if archive.testzip() is not None:
                raise RuntimeError('WordPress ZIP integrity failure')
            for item in archive.infolist():
                target = (workspace / item.filename).resolve()
                if not target.is_relative_to(workspace):
                    raise RuntimeError('Unsafe archive entry')
            archive.extractall(workspace)
        site = workspace / 'wordpress'
        mu = site / 'wp-content/mu-plugins'
        mu.mkdir(exist_ok=True)
        (mu / 'isolated-test.php').write_text("<?php add_filter('pre_wp_mail', '__return_true'); add_filter('pre_http_request', function () { return new WP_Error('isolated_test', 'External HTTP disabled for the isolated test.'); });", encoding='utf-8')
        config = "<?php\ndefine('DB_NAME', 'udc_upgrade_test');\ndefine('DB_USER', 'root');\ndefine('DB_PASSWORD', '');\ndefine('DB_HOST', '127.0.0.1:" + str(port) + "');\ndefine('DB_CHARSET', 'utf8mb4');\ndefine('DB_COLLATE', '');\ndefine('FS_METHOD', 'direct');\ndefine('DISABLE_WP_CRON', true);\ndefine('AUTOMATIC_UPDATER_DISABLED', true);\ndefine('WP_DEBUG', false);\n$table_prefix = 'wp_';\nif (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');\nrequire_once ABSPATH . 'wp-settings.php';\n"
        (site / 'wp-config.php').write_text(config, encoding='utf-8')
        create_db = "mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); $db=new mysqli('127.0.0.1','root','',''," + str(port) + "); $db->query('CREATE DATABASE udc_upgrade_test CHARACTER SET utf8mb4');"
        execute(['php', '-r', create_db])
        old_zip = workspace / 'previous-plugin.zip'
        with old_zip.open('wb') as stream:
            subprocess.run(['git', 'archive', '--format=zip', '--prefix=user-data-collection/', args.old_ref, 'user-data-collection.php', 'includes', 'languages'], cwd=ROOT, stdout=stream, check=True)
        php = r'''<?php
        define('WP_ADMIN', true);
        if ($argv[1] === 'install') define('WP_INSTALLING', true);
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        require __DIR__ . '/wordpress/wp-load.php';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        class Capturing_Upgrader_Skin extends Automatic_Upgrader_Skin {
            public $error_codes = [];
            public function error($errors) {
                if (is_wp_error($errors)) $this->error_codes = array_merge($this->error_codes, $errors->get_error_codes());
                parent::error($errors);
            }
        }
        function ensure($ok, $message) { if (!$ok) throw new RuntimeException($message); echo 'PASS: ' . $message . PHP_EOL; }
        if ($argv[1] === 'install') {
            wp_install('Synthetic upgrade test', 'synthetic_admin', 'synthetic@example.test', false, '', 'synthetic-local-only-password');
            $skin = new Capturing_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);
            $result = $upgrader->install($argv[2]);
            ensure($result === true, 'Historical ZIP installs with WordPress Plugin_Upgrader');
            $result = activate_plugin('user-data-collection/user-data-collection.php');
            ensure(!is_wp_error($result), 'Historical plugin activates');
            $table = $wpdb->prefix . 'udc_submissions';
            $values = ['last_name'=>'Synthetic','first_name'=>'Example','dob'=>'1990-01-01','address'=>'Test address','city'=>'Test city','zip'=>'1000','phone'=>'5550100','health_good'=>1,'health_treatment'=>0,'health_blood_thinners'=>0,'health_allergies'=>1,'health_pregnant'=>0,'liability_accepted'=>1,'appointment_date'=>'2026-11-01','appointment_time'=>'12:00:00','piercing_location'=>'Synthetic service'];
            $columns = $wpdb->get_col("SHOW COLUMNS FROM $table");
            if (in_array('is_confirmed', $columns, true)) $values['is_confirmed'] = 1;
            if (in_array('zip_city', $columns, true)) { $values['zip_city'] = '1000 Test city'; unset($values['city'],$values['zip']); }
            ensure($wpdb->insert($table,$values) === 1, 'Synthetic historical record inserted');
            update_option('udc_design_enabled','1');
            update_option('udc_design_input_text','#123456');
            file_put_contents(__DIR__.'/before.json',json_encode(['version'=>get_option('udc_db_version'),'row'=>$wpdb->get_row("SELECT * FROM $table",ARRAY_A)]));
        } elseif ($argv[1] === 'replace') {
            ensure(is_plugin_active('user-data-collection/user-data-collection.php'), 'Historical plugin is active before replacement');
            $upgrader = new Plugin_Upgrader(new Capturing_Upgrader_Skin());
            $result = $upgrader->install($argv[2]);
            $codes = $upgrader->skin->error_codes;
            echo 'INSTALL_CHECK=' . json_encode(['result_type'=>get_debug_type($result),'errors'=>$codes]) . PHP_EOL;
            ensure(in_array('folder_exists', $codes, true), 'First upload detects the installed folder and requests replacement');
            $upgrader = new Plugin_Upgrader(new Capturing_Upgrader_Skin());
            $result = $upgrader->install($argv[2], ['overwrite_package'=>true]);
            ensure($result === true, 'Confirmed replacement overwrites the existing plugin without manual deletion');
        } elseif ($argv[1] === 'browser') {
            update_option('home',$argv[2]); update_option('siteurl',$argv[2]);
            update_option('udc_design_enabled',0);
            $page=wp_insert_post(['post_title'=>'Synthetic forms','post_content'=>'[udc_tattoo_form][udc_piercing_form]','post_type'=>'page','post_status'=>'publish']);
            $uploads=wp_upload_dir(); wp_mkdir_p($uploads['path']);
            $file=$uploads['path'].'/guardian-browser.pdf'; file_put_contents($file,"%PDF-1.4\n%%EOF\n");
            $attachment=wp_insert_attachment(['post_title'=>'Guardian browser fixture','post_mime_type'=>'application/pdf','post_status'=>'inherit'],$file);
            foreach(['fr','de','it','en','es'] as $language) update_option('udc_guardian_document_'.$language,$attachment);
            echo json_encode(['page'=>$page,'attachment'=>$attachment]);
        } elseif ($argv[1] === 'browser-verify') {
            $table=$wpdb->prefix.'udc_submissions';
            foreach(['tattoo','piercing'] as $type) {
                ensure((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE form_type=%s AND minor_declared=1",$type))===1,'Native browser submission persisted exactly once: '.$type);
            }
        } else {
            if (!is_plugin_active('user-data-collection/user-data-collection.php')) {
                ensure(!is_wp_error(activate_plugin('user-data-collection/user-data-collection.php')), 'Updated plugin can be activated');
            }
            $before=json_decode(file_get_contents(__DIR__.'/before.json'),true);
            $table=$wpdb->prefix.'udc_submissions';
            $after=$wpdb->get_row("SELECT * FROM $table",ARRAY_A);
            ensure(UDC_DB_VERSION === '1.12.0' && get_option('udc_db_version') === UDC_DB_VERSION, 'New plugin and real database schema reach 1.12.0');
            ensure((int)$wpdb->get_var("SELECT COUNT(*) FROM $table") === 1, 'Existing record count is preserved');
            foreach($before['row'] as $key=>$value) {
                if (in_array($key, ['is_confirmed','address','city','zip','zip_city'], true)) continue;
                ensure(array_key_exists($key,$after) && (string)$after[$key] === (string)$value, 'Existing field preserved: '.$key);
            }
            ensure($after['form_type'] === 'legacy', 'Historical record is retained as legacy');
            $columns=$wpdb->get_col("SHOW COLUMNS FROM $table");
            ensure(!array_intersect(['address','city','zip'], $columns), 'Retired contact columns are physically removed');
            ensure(array_key_exists('minor_declared', $after) && null === $after['minor_declared'], 'Historical minority declaration remains unknown');
            $backup=new UDC_Backup();
            $validate=new ReflectionMethod($backup,'validate_backup_rows');
            $validate->setAccessible(true);
            $normalized=$validate->invoke($backup,[$before['row']]);
            ensure(!is_wp_error($normalized) && !array_intersect(['address','city','zip'],array_keys($normalized[0])), 'Historical backup normalization discards retired fields');
            $normalized[0]['id']=999;
            $restore=new ReflectionMethod($backup,'insert_backup_data');
            $restore->setAccessible(true);
            ensure($restore->invoke($backup,$normalized)===1, 'Historical backup inserts into the real reduced schema');
            ensure((int)$wpdb->get_var("SELECT COUNT(*) FROM $table")===2, 'Restore adds exactly one synthetic record');
            $uploads=wp_upload_dir();
            wp_mkdir_p($uploads['path']);
            $media_file=$uploads['path'].'/guardian-example.pdf';
            file_put_contents($media_file, "%PDF-1.4\n%%EOF\n");
            $attachment=wp_insert_attachment(['post_title'=>'Synthetic guardian template','post_mime_type'=>'application/pdf','post_status'=>'inherit'],$media_file);
            ensure(UDC_Guardian::valid_attachment($attachment), 'Native WordPress PDF attachment passes validation');
            UDC_Guardian::register_settings();
            foreach(['fr','de','it','en','es'] as $language) {
                update_option('udc_guardian_document_'.$language,$attachment);
                ensure((int)get_option('udc_guardian_document_'.$language)===$attachment,'Native per-language media setting persists: '.$language);
            }
            update_option('udc_guardian_document_fr',999999);
            ensure((int)get_option('udc_guardian_document_fr')===$attachment,'Invalid native attachment selection preserves the previous option');
            wp_delete_attachment($attachment,true);
            ensure(!UDC_Guardian::valid_attachment($attachment),'Deleted native attachment becomes unavailable');
            ensure(get_option('udc_design_enabled') === '1' && get_option('udc_design_input_text') === '#123456', 'Existing settings are preserved');
            echo 'WORDPRESS_VERSION=' . $wp_version . PHP_EOL;
            echo 'MYSQL_VERSION=' . $wpdb->db_version() . PHP_EOL;
            echo 'OLD_PLUGIN_SCHEMA=' . $before['version'] . PHP_EOL;
        }
        '''
        runner = workspace / 'exercise.php'
        runner.write_text(php, encoding='utf-8')
        for phase, archive in [('install',old_zip),('replace',package),('verify',package)]:
            print('Phase:', phase, flush=True)
            output=execute(['php',str(runner),phase,str(archive)],cwd=site)
            print(output,flush=True)
        print('PASS: real WordPress ZIP replacement and data/settings preservation', flush=True)
        if args.browser:
            with socket.socket() as sock:
                sock.bind(('127.0.0.1',0)); web_port=sock.getsockname()[1]
            origin='http://127.0.0.1:'+str(web_port)
            setup=json.loads(execute(['php',str(runner),'browser',origin],cwd=site))
            web_log=(workspace/'http.log').open('wb')
            web_server=subprocess.Popen(['php','-S','127.0.0.1:'+str(web_port),'-t',str(site)],stdout=web_log,stderr=subprocess.STDOUT,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
            try:
                print(execute(['node',str(ROOT/'tests/guardian-native-browser.cjs'),origin,str(setup['page'])],cwd=ROOT),flush=True)
                print(execute(['php',str(runner),'browser-verify',origin],cwd=site),flush=True)
            finally:
                web_server.terminate(); web_server.wait(timeout=10); web_log.close()
    finally:
        shutdown = "mysqli_report(MYSQLI_REPORT_OFF); $db=@new mysqli('127.0.0.1','root','',''," + str(port) + "); if (!$db->connect_errno) { @$db->query('SHUTDOWN'); }"
        subprocess.run(['php', '-r', shutdown], capture_output=True)
        try:
            server.wait(timeout=10)
        except subprocess.TimeoutExpired:
            server.terminate()
        try:
            server.wait(timeout=15)
        except subprocess.TimeoutExpired:
            server.kill()
            server.wait(timeout=5)
        mysql_log.close()
        # Only remove the database directory created by this process, after its server has stopped.
        target=data.resolve()
        if target.parent == workspace and target.name == 'mysql-data' and workspace.name.startswith('udc-wordpress-upgrade-'):
            for attempt in range(10):
                try:
                    shutil.rmtree(target)
                    break
                except PermissionError:
                    time.sleep(.5)
            else:
                print('Temporary database cleanup pending: ' + str(target), flush=True)
        print('Temporary MySQL stopped; temporary database files removed.',flush=True)

if __name__ == '__main__':
    main()
