<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - Restaurant POS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            direction: rtl;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            position: relative;
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 40px;
            text-align: center;
            color: white;
        }

        .login-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .restaurant-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .login-form {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            direction: rtl;
            text-align: right;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control.error {
            border-color: #e74c3c;
        }

        .form-error {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }

        .checkbox-group input[type="checkbox"] {
            margin-left: 8px;
            transform: scale(1.2);
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-size: 14px;
            color: #666;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .demo-accounts {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }

        .demo-accounts h4 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }

        .demo-account {
            background: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 1px solid #e9ecef;
            font-size: 13px;
        }

        .demo-account:last-child {
            margin-bottom: 0;
        }

        .demo-account strong {
            color: #667eea;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
        }

        .language-switch {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 100;
        }

        .language-switch a {
            color: white;
            text-decoration: none;
            padding: 8px 12px;
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .language-switch a:hover {
            background: rgba(255,255,255,0.3);
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                max-width: none;
            }
            
            .login-form {
                padding: 30px 25px;
            }
            
            .login-header {
                padding: 25px 25px;
            }
        }
    </style>
</head>
<body>
    <div class="language-switch">
        <a href="/language/en">English</a>
    </div>

    <div class="login-container">
        <div class="login-header">
            <div class="restaurant-icon">🍽️</div>
            <h1>Restaurant POS</h1>
            <p>نظام نقاط البيع للمطاعم</p>
        </div>

        <div class="login-form">
            <?php if (!empty($success)): ?>
                <?php foreach ($success as $message): ?>
                    <div class="alert alert-success">
                        <?php echo e($message); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $message): ?>
                    <div class="alert alert-error">
                        <?php echo e($message); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="POST" action="/login">
                <?php echo csrf_field(); ?>
                
                <div class="form-group">
                    <label for="username">اسم المستخدم</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-control" 
                        value="<?php echo e(old('username', $oldInput['username'] ?? '')); ?>"
                        required
                        autocomplete="username"
                        placeholder="أدخل اسم المستخدم"
                    >
                </div>

                <div class="form-group">
                    <label for="password">كلمة المرور</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        required
                        autocomplete="current-password"
                        placeholder="أدخل كلمة المرور"
                    >
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <label for="remember">تذكرني</label>
                </div>

                <button type="submit" class="btn-login">
                    تسجيل الدخول
                </button>
            </form>

            <div class="demo-accounts">
                <h4>حسابات تجريبية</h4>
                <div class="demo-account">
                    <strong>مدير:</strong> admin / admin123
                </div>
                <div class="demo-account">
                    <strong>كاشير:</strong> cashier / cashier123
                </div>
                <div class="demo-account">
                    <strong>نادل:</strong> waiter / waiter123
                </div>
                <div class="demo-account">
                    <strong>مطبخ:</strong> kitchen / kitchen123
                </div>
            </div>
        </div>

        <div class="footer">
            <p>© 2025 Restaurant POS System. جميع الحقوق محفوظة.</p>
            <p>نظام نقاط البيع للمطاعم - النسخة 1.0</p>
        </div>
    </div>

    <script>
        // Auto-focus on username field
        document.getElementById('username').focus();

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!username) {
                alert('يرجى إدخال اسم المستخدم');
                e.preventDefault();
                return;
            }

            if (!password) {
                alert('يرجى إدخال كلمة المرور');
                e.preventDefault();
                return;
            }

            // Disable submit button to prevent double submission
            const submitBtn = document.querySelector('.btn-login');
            submitBtn.disabled = true;
            submitBtn.textContent = 'جاري تسجيل الدخول...';
        });

        // Clear errors on input
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('error');
            });
        });

        // Language switch handling
        document.querySelector('.language-switch a').addEventListener('click', function(e) {
            // Store current language preference
            localStorage.setItem('preferredLanguage', this.textContent.trim());
        });
    </script>
</body>
</html>