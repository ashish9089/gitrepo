<?php
session_start();
require __DIR__ . '/config.php';

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect($url = 'index.php') { header('Location: ' . $url); exit; }
function flash($message, $type='success') { $_SESSION['flash'] = [$message, $type]; }
function require_login() { if (empty($_SESSION['user'])) redirect('index.php?page=login'); }
function is_manager() { return !empty($_SESSION['user']) && $_SESSION['user']['role'] === 'manager'; }

$page = $_GET['page'] ?? (isset($_SESSION['user']) ? 'dashboard' : 'login');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'register') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            if (strlen($username) < 3 || strlen($password) < 8) throw new RuntimeException('Username must be at least 3 characters and password at least 8 characters.');
            $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            // First account becomes manager. Later public registrations are operators.
            $role = $count === 0 ? 'manager' : 'operator';
            $stmt = $pdo->prepare('INSERT INTO users (username,password_hash,role) VALUES (?,?,?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
            flash($count === 0 ? 'Account created. You are the first manager.' : 'Operator account created.');
            redirect('index.php?page=login');
        }
        if ($action === 'login') {
            $stmt = $pdo->prepare('SELECT id,username,password_hash,role FROM users WHERE username=?');
            $stmt->execute([trim($_POST['username'] ?? '')]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($_POST['password'] ?? '', $user['password_hash'])) throw new RuntimeException('Incorrect username or password.');
            session_regenerate_id(true);
            $_SESSION['user'] = ['id'=>$user['id'], 'username'=>$user['username'], 'role'=>$user['role']];
            redirect('index.php?page=dashboard');
        }
        require_login();
        if ($action === 'logout') { $_SESSION = []; session_destroy(); redirect('index.php?page=login'); }
        if ($action === 'add_machine') {
            $no = trim($_POST['machine_no'] ?? '');
            if ($no === '') throw new RuntimeException('Enter a machine number.');
            $stmt = $pdo->prepare('INSERT INTO machines(machine_no,created_by) VALUES(?,?)');
            $stmt->execute([$no, $_SESSION['user']['id']]);
            flash('Machine added successfully.'); redirect('index.php?page=dashboard');
        }
        if ($action === 'delete_machine') {
            if (!is_manager()) throw new RuntimeException('Only managers can delete machines.');
            $stmt = $pdo->prepare('DELETE FROM machines WHERE id=?'); $stmt->execute([(int)$_POST['machine_id']]);
            flash('Machine and its programs deleted.'); redirect('index.php?page=dashboard');
        }
        if ($action === 'add_program') {
            $machineId = (int)($_POST['machine_id'] ?? 0);
            $programNo = trim($_POST['program_no'] ?? '');
            $figure = trim($_POST['figure'] ?? '');
            if (!$machineId || $programNo === '' || $figure === '') throw new RuntimeException('Machine, program number and figure are required.');
            $stmt = $pdo->prepare('INSERT INTO programs(machine_id,program_no,figure,f1,f2,f3,created_by) VALUES(?,?,?,?,?,?,?)');
            $stmt->execute([$machineId,$programNo,$figure,trim($_POST['f1'] ?? ''),trim($_POST['f2'] ?? ''),trim($_POST['f3'] ?? ''),$_SESSION['user']['id']]);
            flash('Program saved successfully.'); redirect('index.php?page=machine&id='.$machineId);
        }
        if ($action === 'mark_done') {
            $stmt = $pdo->prepare("UPDATE programs SET status='Done' WHERE id=?"); $stmt->execute([(int)$_POST['program_id']]);
            flash('Program marked as done.'); redirect('index.php?page=machine&id='.(int)$_POST['machine_id']);
        }
        if ($action === 'delete_program') {
            if (!is_manager()) throw new RuntimeException('Only managers can delete programs.');
            $stmt = $pdo->prepare('DELETE FROM programs WHERE id=?'); $stmt->execute([(int)$_POST['program_id']]);
            flash('Program deleted.'); redirect('index.php?page=machine&id='.(int)$_POST['machine_id']);
        }
        if ($action === 'change_role') {
            if (!is_manager()) throw new RuntimeException('Manager access required.');
            $role = ($_POST['role'] ?? '') === 'manager' ? 'manager' : 'operator';
            $stmt = $pdo->prepare('UPDATE users SET role=? WHERE id=?'); $stmt->execute([$role,(int)$_POST['user_id']]);
            flash('User role updated.'); redirect('index.php?page=users');
        }
    } catch (PDOException $ex) {
        $error = str_contains($ex->getMessage(), 'unique') ? 'That machine or program number already exists.' : 'Unable to save. Please check the entered details.';
    } catch (Throwable $ex) { $error = $ex->getMessage(); }
}

if ($page !== 'login' && $page !== 'register') require_login();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$machine = null; $programs = []; $program = null;
if (isset($_SESSION['user']) && $page === 'machine') {
    $stmt=$pdo->prepare('SELECT * FROM machines WHERE id=?'); $stmt->execute([(int)($_GET['id']??0)]); $machine=$stmt->fetch();
    if (!$machine) { flash('Machine not found.','danger'); redirect('index.php?page=dashboard'); }
    $stmt=$pdo->prepare('SELECT p.*,u.username AS creator FROM programs p LEFT JOIN users u ON u.id=p.created_by WHERE p.machine_id=? ORDER BY p.created_at DESC'); $stmt->execute([$machine['id']]); $programs=$stmt->fetchAll();
}
if (isset($_SESSION['user']) && $page === 'program') {
    $stmt=$pdo->prepare('SELECT p.*,m.machine_no,u.username AS creator FROM programs p JOIN machines m ON m.id=p.machine_id LEFT JOIN users u ON u.id=p.created_by WHERE p.id=?');
    $stmt->execute([(int)($_GET['id']??0)]); $program=$stmt->fetch();
    if (!$program) { flash('Program not found.','danger'); redirect('index.php?page=dashboard'); }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Machinex | Production Manager</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="assets/style.css" rel="stylesheet"></head>
<body>
<?php if (isset($_SESSION['user'])): ?>
<nav class="navbar navbar-expand-lg navbar-dark app-nav"><div class="container-fluid px-lg-4"><a class="navbar-brand fw-bold" href="index.php"><span class="brand-mark"><i class="bi bi-cpu"></i></span> MACHINEX</a><button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button><div class="collapse navbar-collapse" id="nav"><ul class="navbar-nav me-auto"><li class="nav-item"><a class="nav-link" href="index.php?page=dashboard">Dashboard</a></li><?php if(is_manager()): ?><li class="nav-item"><a class="nav-link" href="index.php?page=users">Users</a></li><?php endif; ?></ul><div class="d-flex align-items-center gap-3 text-white"><div class="small text-end"><div class="fw-semibold"><?=e($_SESSION['user']['username'])?></div><span class="opacity-75 text-capitalize"><?=e($_SESSION['user']['role'])?></span></div><form method="post"><input type="hidden" name="action" value="logout"><button class="btn btn-sm btn-outline-light">Logout</button></form></div></div></div></nav>
<?php endif; ?>
<main class="<?=isset($_SESSION['user'])?'container-fluid px-3 px-lg-4 py-4':'auth-shell'?>">
<?php if($error): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?=e($error)?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if($flash): ?><div class="alert alert-<?=e($flash[1])?> alert-dismissible fade show" role="alert"><?=e($flash[0])?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if($page==='login' || $page==='register'): ?>
<div class="auth-card"><div class="auth-brand"><div class="brand-mark large"><i class="bi bi-cpu"></i></div><div><div class="h4 fw-bold mb-0">MACHINEX</div><div class="text-secondary small">Production program management</div></div></div>
<?php if($page==='login'): ?><h1 class="h3 fw-bold mt-4">Welcome back</h1><p class="text-secondary">Sign in to manage your machines and programs.</p><form method="post" class="mt-4"><input type="hidden" name="action" value="login"><label class="form-label">Username</label><input class="form-control form-control-lg mb-3" name="username" required autocomplete="username"><label class="form-label">Password</label><input type="password" class="form-control form-control-lg mb-4" name="password" required autocomplete="current-password"><button class="btn btn-primary btn-lg w-100">Sign in <i class="bi bi-arrow-right ms-1"></i></button></form><p class="text-center mt-4 mb-0 text-secondary">New here? <a href="index.php?page=register">Create an account</a></p>
<?php else: ?><h1 class="h3 fw-bold mt-4">Create account</h1><p class="text-secondary">The first account becomes the manager. Later public registrations are operators.</p><form method="post" class="mt-4"><input type="hidden" name="action" value="register"><label class="form-label">Username</label><input class="form-control form-control-lg mb-3" name="username" minlength="3" required><label class="form-label">Password</label><input type="password" class="form-control form-control-lg mb-4" name="password" minlength="8" required><button class="btn btn-primary btn-lg w-100">Create account</button></form><p class="text-center mt-4 mb-0 text-secondary">Already registered? <a href="index.php?page=login">Sign in</a></p><?php endif; ?></div>
<?php elseif($page==='dashboard'):
$machines=$pdo->query('SELECT m.*,COUNT(p.id) AS program_count,SUM(p.status="Done") AS done_count FROM machines m LEFT JOIN programs p ON p.machine_id=m.id GROUP BY m.id ORDER BY m.created_at DESC')->fetchAll();
$totalPrograms=(int)$pdo->query('SELECT COUNT(*) FROM programs')->fetchColumn(); $donePrograms=(int)$pdo->query("SELECT COUNT(*) FROM programs WHERE status='Done'")->fetchColumn(); ?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><div class="eyebrow">WORKSPACE / OVERVIEW</div><h1 class="page-title">Machine dashboard</h1><p class="text-secondary mb-0">Monitor your machines and production programs.</p></div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMachine"><i class="bi bi-plus-lg me-1"></i> Add machine</button></div>
<div class="row g-3 mb-4"><div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon blue"><i class="bi bi-cpu"></i></div><div class="stat-label">Total machines</div><div class="stat-value"><?=count($machines)?></div></div></div><div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon purple"><i class="bi bi-boxes"></i></div><div class="stat-label">Total programs</div><div class="stat-value"><?=$totalPrograms?></div></div></div><div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon green"><i class="bi bi-check2-circle"></i></div><div class="stat-label">Completed</div><div class="stat-value"><?=$donePrograms?></div></div></div><div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div><div class="stat-label">Pending</div><div class="stat-value"><?=$totalPrograms-$donePrograms?></div></div></div></div>
<div class="section-heading"><div><h2 class="h5 fw-bold mb-1">Your machines</h2><p class="text-secondary small mb-0">Select a machine to view its programs.</p></div><span class="small text-secondary"><?=count($machines)?> machines</span></div>
<div class="row g-3"><?php foreach($machines as $m): ?><div class="col-sm-6 col-xl-4 col-xxl-3"><div class="machine-card h-100"><div class="d-flex justify-content-between"><div class="machine-icon"><i class="bi bi-cpu"></i></div><span class="badge rounded-pill text-bg-light">ACTIVE</span></div><h3 class="h5 fw-bold mt-3 mb-1"><?=e($m['machine_no'])?></h3><p class="text-secondary small mb-3">Added <?=date('d M Y',strtotime($m['created_at']))?></p><div class="d-flex gap-4 mb-3"><div><div class="metric-number"><?=$m['program_count']?></div><div class="metric-label">Programs</div></div><div><div class="metric-number"><?=$m['done_count']??0?></div><div class="metric-label">Completed</div></div></div><a class="btn btn-light w-100" href="index.php?page=machine&id=<?=$m['id']?>">Open machine <i class="bi bi-arrow-up-right ms-1"></i></a><?php if(is_manager()): ?><form method="post" class="text-end mt-2" onsubmit="return confirm('Delete this machine and all its programs?')"><input type="hidden" name="action" value="delete_machine"><input type="hidden" name="machine_id" value="<?=$m['id']?>"><button class="btn btn-link btn-sm text-danger text-decoration-none">Delete machine</button></form><?php endif; ?></div></div><?php endforeach; ?>
<?php if(!$machines): ?><div class="col-12"><div class="empty-state"><div class="empty-icon"><i class="bi bi-cpu"></i></div><h3 class="h5 fw-bold">No machines yet</h3><p class="text-secondary">Add your first machine to start organizing production programs.</p><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMachine">Add first machine</button></div></div><?php endif; ?></div>
<div class="modal fade" id="addMachine" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title fw-bold">Add new machine</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="add_machine"><label class="form-label">Machine number / name</label><input class="form-control" name="machine_no" placeholder="e.g. M1" required autofocus></div><div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal" type="button">Cancel</button><button class="btn btn-primary">Save machine</button></div></form></div></div></div>
<?php elseif($page==='machine' && $machine): ?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><a class="back-link" href="index.php?page=dashboard"><i class="bi bi-arrow-left"></i> All machines</a><div class="eyebrow mt-3">MACHINE / PROGRAMS</div><h1 class="page-title mb-1"><?=e($machine['machine_no'])?></h1><p class="text-secondary mb-0">Manage programs assigned to this machine.</p></div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProgram"><i class="bi bi-plus-lg me-1"></i> Add program</button></div>
<div class="section-heading"><div><h2 class="h5 fw-bold mb-1">Program list</h2><p class="text-secondary small mb-0">Open a program to view, complete or print its details.</p></div><span class="small text-secondary"><?=count($programs)?> programs</span></div>
<div class="row g-3"><?php foreach($programs as $p): ?><div class="col-md-6 col-xl-4 col-xxl-3"><a class="program-card d-block" href="index.php?page=program&id=<?=$p['id']?>"><div class="d-flex justify-content-between align-items-start"><div class="program-icon"><i class="bi bi-file-earmark-text"></i></div><span class="status-pill <?=$p['status']==='Done'?'done':'pending'?>"><span></span><?=e($p['status'])?></span></div><div class="h5 fw-bold mt-3 mb-1">Program <?=e($p['program_no'])?></div><div class="text-secondary small">Figure: <?=e($p['figure'])?></div><div class="program-fields mt-3"><span>F1: <?=e($p['f1']?:'—')?></span><span>F2: <?=e($p['f2']?:'—')?></span><span>F3: <?=e($p['f3']?:'—')?></span></div><div class="d-flex justify-content-between align-items-center border-top pt-3 mt-3 small text-secondary"><span><?=date('d M Y',strtotime($p['created_at']))?></span><span class="text-primary fw-semibold">View details <i class="bi bi-arrow-right"></i></span></div></a></div><?php endforeach; ?>
<?php if(!$programs): ?><div class="col-12"><div class="empty-state"><div class="empty-icon"><i class="bi bi-file-earmark-plus"></i></div><h3 class="h5 fw-bold">No programs for this machine</h3><p class="text-secondary">Create the first program for <?=e($machine['machine_no'])?>.</p><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProgram">Add program</button></div></div><?php endif; ?></div>
<div class="modal fade" id="addProgram" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title fw-bold">Add program</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="add_program"><input type="hidden" name="machine_id" value="<?=$machine['id']?>"><label class="form-label">Program number</label><input class="form-control mb-3" name="program_no" placeholder="e.g. P1" required><label class="form-label">Figure</label><select class="form-select mb-3" name="figure" id="figureSelect" required><option value="">Select figure</option><?php foreach(['Figure 1','Figure 2','Figure 3','Figure 4','Figure 5','Other'] as $f): ?><option><?=e($f)?></option><?php endforeach; ?></select><div class="row g-3"><div class="col-12"><label class="form-label">F1</label><input class="form-control" name="f1" placeholder="Enter F1 value"></div><div class="col-12"><label class="form-label">F2</label><input class="form-control" name="f2" placeholder="Enter F2 value"></div><div class="col-12"><label class="form-label">F3</label><input class="form-control" name="f3" placeholder="Enter F3 value"></div></div><div class="form-text mt-2">The figure choices are starter options; adjust them to match your actual production figures.</div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save program</button></div></form></div></div></div>
<?php elseif($page==='program' && $program): ?>
<div class="mb-4"><a class="back-link" href="index.php?page=machine&id=<?=$program['machine_id']?>"><i class="bi bi-arrow-left"></i> Back to <?=e($program['machine_no'])?></a><div class="eyebrow mt-3">PROGRAM / DETAILS</div><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><h1 class="page-title mb-1">Program <?=e($program['program_no'])?></h1><p class="text-secondary mb-0">Machine <?=e($program['machine_no'])?> · Created <?=date('d M Y, h:i A',strtotime($program['created_at']))?></p></div><span class="status-pill large <?=$program['status']==='Done'?'done':'pending'?>"><span></span><?=e($program['status'])?></span></div></div>
<div class="row g-4"><div class="col-lg-8"><div class="detail-panel"><div class="panel-heading"><div><div class="eyebrow">CONFIGURATION</div><h2 class="h5 fw-bold mb-0">Program parameters</h2></div><i class="bi bi-sliders2 text-secondary fs-4"></i></div><div class="row g-3 mt-1"><div class="col-sm-6"><div class="detail-field"><div class="detail-label">Program number</div><div class="detail-value"><?=e($program['program_no'])?></div></div></div><div class="col-sm-6"><div class="detail-field"><div class="detail-label">Figure</div><div class="detail-value"><?=e($program['figure'])?></div></div></div><?php foreach(['f1'=>'F1','f2'=>'F2','f3'=>'F3'] as $key=>$label): ?><div class="col-sm-4"><div class="detail-field"><div class="detail-label"><?=$label?></div><div class="detail-value"><?=e($program[$key]?:'—')?></div></div></div><?php endforeach; ?></div></div></div><div class="col-lg-4"><div class="detail-panel action-panel"><div class="eyebrow">ACTIONS</div><h2 class="h5 fw-bold">Program workflow</h2><p class="text-secondary small">Update the production status or print a copy for the connected computer printer.</p><?php if($program['status']!=='Done'): ?><form method="post" onsubmit="return confirm('Mark this program as completed?')"><input type="hidden" name="action" value="mark_done"><input type="hidden" name="program_id" value="<?=$program['id']?>"><input type="hidden" name="machine_id" value="<?=$program['machine_id']?>"><button class="btn btn-success w-100 mb-2"><i class="bi bi-check2-circle me-1"></i> Mark as done</button></form><?php else: ?><div class="alert alert-success small"><i class="bi bi-check-circle-fill me-1"></i> This program is completed.</div><?php endif; ?><button class="btn btn-outline-primary w-100 mb-2" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print program</button><?php if(is_manager()): ?><form method="post" onsubmit="return confirm('Permanently delete this program?')"><input type="hidden" name="action" value="delete_program"><input type="hidden" name="program_id" value="<?=$program['id']?>"><input type="hidden" name="machine_id" value="<?=$program['machine_id']?>"><button class="btn btn-link text-danger w-100">Delete program</button></form><?php endif; ?></div></div></div>
<div class="print-only"><h2>MACHINEX - Production Program</h2><hr><p><b>Machine:</b> <?=e($program['machine_no'])?></p><p><b>Program number:</b> <?=e($program['program_no'])?></p><p><b>Figure:</b> <?=e($program['figure'])?></p><p><b>F1:</b> <?=e($program['f1'])?>　<b>F2:</b> <?=e($program['f2'])?>　<b>F3:</b> <?=e($program['f3'])?></p><p><b>Status:</b> <?=e($program['status'])?></p></div>
<?php elseif($page==='users' && is_manager()): $users=$pdo->query('SELECT id,username,role,created_at FROM users ORDER BY created_at DESC')->fetchAll(); ?>
<div class="mb-4"><div class="eyebrow">ADMINISTRATION</div><h1 class="page-title">User management</h1><p class="text-secondary">Review accounts and manage access levels.</p></div><div class="detail-panel"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Username</th><th>Role</th><th>Joined</th><th>Update role</th></tr></thead><tbody><?php foreach($users as $u): ?><tr><td class="fw-semibold"><?=e($u['username'])?> <?php if($u['id']==$_SESSION['user']['id']): ?><span class="badge text-bg-light">You</span><?php endif; ?></td><td><span class="badge <?=$u['role']==='manager'?'text-bg-primary':'text-bg-light'?> text-capitalize"><?=e($u['role'])?></span></td><td><?=date('d M Y',strtotime($u['created_at']))?></td><td><form method="post" class="d-flex gap-2"><input type="hidden" name="action" value="change_role"><input type="hidden" name="user_id" value="<?=$u['id']?>"><select name="role" class="form-select form-select-sm" style="max-width:140px"><option value="operator" <?=$u['role']==='operator'?'selected':''?>>Operator</option><option value="manager" <?=$u['role']==='manager'?'selected':''?>>Manager</option></select><button class="btn btn-sm btn-outline-primary">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php else: ?><div class="empty-state"><h3 class="h5">Page not found</h3><a href="index.php?page=dashboard">Return to dashboard</a></div><?php endif; ?>
</main><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
