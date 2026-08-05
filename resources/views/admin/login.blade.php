<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>De-Light Admin — Login</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#0F172A 0%,#1E3A8A 100%);min-height:100vh;display:flex;align-items:center;justify-content:center}
  .card{background:white;border-radius:20px;padding:40px;width:100%;max-width:420px;box-shadow:0 25px 50px rgba(0,0,0,0.3)}
  .logo{text-align:center;margin-bottom:28px}
  .logo-icon{width:64px;height:64px;background:linear-gradient(135deg,#1E3A8A,#3B82F6);border-radius:16px;display:inline-flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:12px}
  .logo h1{font-size:22px;font-weight:800;color:#0F172A}
  .logo p{color:#64748B;font-size:13px;margin-top:4px}
  .badge{background:#EF4444;color:white;font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;margin-left:6px;vertical-align:middle}
  label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px}
  input{width:100%;padding:13px 16px;border:1.5px solid #E5E7EB;border-radius:12px;font-size:14px;outline:none;transition:border 0.2s;color:#0F172A}
  input:focus{border-color:#3B82F6;box-shadow:0 0 0 3px rgba(59,130,246,0.1)}
  .form-group{margin-bottom:18px}
  .error{background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;padding:12px;border-radius:10px;font-size:13px;margin-bottom:16px}
  btn{display:block;width:100%;padding:14px;background:linear-gradient(135deg,#1E3A8A,#3B82F6);color:white;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;margin-top:8px;transition:opacity 0.2s}
  .btn:hover{opacity:0.9}
  .hint{text-align:center;color:#94A3B8;font-size:12px;margin-top:20px}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon">🏪</div>
    <h1>De-Light <span class="badge">ADMIN</span></h1>
    <p>SaaS Platform Control Panel</p>
  </div>

  @if($errors->any())
  <div class="error">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="/admin/login">
    @csrf
    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email" value="{{ old('email') }}" placeholder="admin@delight.app" required autofocus>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="••••••••••••" required>
    </div>
    <button type="submit" class="btn">Sign In to Admin Panel</button>
  </form>

  <p class="hint">🔒 Restricted access — authorized personnel only</p>
</div>
</body>
</html>
