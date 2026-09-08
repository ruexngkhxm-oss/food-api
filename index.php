<?php
/**
 * index.php
 * Food Recipe REST API Portal & Server Status Dashboard
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Recipe REST API - เชฟเตวรากรหมู่ 6 (Render Production)</title>
    
    <!-- Google Fonts: Kanit & Sarabun -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #FF6B35;
            --primary-dark: #E85A24;
            --secondary: #2EC4B6;
            --bg-dark: #0F172A;
            --card-bg: #1E293B;
            --text-main: #F8FAFC;
            --text-sub: #94A3B8;
            --success: #10B981;
            --border: #334155;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Kanit', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;
        }

        .portal-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            max-width: 850px;
            width: 100%;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .header {
            text-align: center;
            margin-bottom: 32px;
        }

        .chef-avatar {
            font-size: 64px;
            margin-bottom: 12px;
            display: inline-block;
            filter: drop-shadow(0 4px 12px rgba(255, 107, 53, 0.3));
        }

        .title {
            font-size: 28px;
            font-weight: 700;
            color: #FFFFFF;
            margin-bottom: 8px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13.5px;
            font-weight: 600;
        }

        .pulse {
            width: 8px;
            height: 8px;
            background: var(--success);
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulseAnimation 2s infinite;
        }

        @keyframes pulseAnimation {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .endpoints-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }

        .endpoint-card {
            background: #0F172A;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px;
            transition: all 0.2s ease;
            text-decoration: none;
            color: inherit;
        }

        .endpoint-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .method {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            margin-bottom: 8px;
        }

        .method-get { background: rgba(46, 196, 182, 0.2); color: var(--secondary); }
        .method-post { background: rgba(255, 107, 53, 0.2); color: var(--primary); }

        .endpoint-url {
            font-family: monospace;
            font-size: 14px;
            font-weight: 600;
            color: #FFFFFF;
            margin-bottom: 4px;
        }

        .endpoint-desc {
            font-size: 12.5px;
            color: var(--text-sub);
        }

        .info-box {
            background: rgba(255, 107, 53, 0.08);
            border: 1px dashed var(--primary);
            border-radius: 16px;
            padding: 16px 20px;
            margin-top: 28px;
            font-size: 13.5px;
            color: var(--text-sub);
            line-height: 1.6;
        }
    </style>
</head>
<body>

<div class="portal-card">
    <div class="header">
        <div class="chef-avatar">👨‍🍳</div>
        <h1 class="title">Food Recipe REST API Engine</h1>
        <p style="color: var(--text-sub); font-size: 14.5px; margin-bottom: 14px;">ระบบหลังบ้านประมวลผลสูตรอาหารและซิงค์ข้อมูลเรียลไทม์กับแอปมือถือ</p>
        <div class="status-badge">
            <span class="pulse"></span>
            Render Server Online & Ready (SQLite/MySQL Engine)
        </div>
    </div>

    <div style="font-weight: 600; font-size: 16px; color: #FFFFFF; margin-bottom: 12px;">📡 รายการ REST API Endpoints พร้อมใช้งาน:</div>

    <div class="endpoints-grid">
        <a href="get_chef_recipes.php?user_id=3" target="_blank" class="endpoint-card">
            <span class="method method-get">GET</span>
            <div class="endpoint-url">/get_chef_recipes.php?user_id=3</div>
            <div class="endpoint-desc">ดึงรายการสูตรอาหารทั้งหมดของเชฟเตวรากรหมู่ 6</div>
        </a>

        <a href="get_recipes.php" target="_blank" class="endpoint-card">
            <span class="method method-get">GET</span>
            <div class="endpoint-url">/get_recipes.php</div>
            <div class="endpoint-desc">ดึงรายการสูตรอาหารทั้งหมดสำหรับแสดงผลบนแอปมือถือ</div>
        </a>

        <a href="get_recipe_detail.php?id=1" target="_blank" class="endpoint-card">
            <span class="method method-get">GET</span>
            <div class="endpoint-url">/get_recipe_detail.php?id=1</div>
            <div class="endpoint-desc">ดึงรายละเอียดสูตร วัตถุดิบ และขั้นตอนการทำ</div>
        </a>

        <a href="get_chef_analytics.php?chef_id=3" target="_blank" class="endpoint-card">
            <span class="method method-get">GET</span>
            <div class="endpoint-url">/get_chef_analytics.php?chef_id=3</div>
            <div class="endpoint-desc">ดึงข้อมูลสถิติ ยอดชม ผู้ติดตาม และคะแนนรีวิว</div>
        </a>

        <a href="get_comments.php?recipe_id=1" target="_blank" class="endpoint-card">
            <span class="method method-get">GET</span>
            <div class="endpoint-url">/get_comments.php?recipe_id=1</div>
            <div class="endpoint-desc">ดึงความคิดเห็น รีวิว และคำตอบกลับของเชฟ</div>
        </a>

        <div class="endpoint-card" style="cursor: default;">
            <span class="method method-post">POST</span>
            <div class="endpoint-url">/add_recipe.php</div>
            <div class="endpoint-desc">สร้างหรือแก้ไขสูตรอาหาร (ลบ/อัปเดตขั้นตอนเรียลไทม์)</div>
        </div>
    </div>

    <div class="info-box">
        💡 <strong>คำแนะนำการใช้งาน:</strong><br>
        • หน้าเว็บนี้คือ <strong>REST API Backend Service</strong> สำหรับประมวลผลข้อมูล<br>
        • หากต้องการใช้งาน <strong>Chef Web Portal UI</strong> สำหรับเชฟเต ให้เปิดไฟล์ <code style="color: var(--primary);">chef_web/index.html</code> ในเครื่องของคุณ<br>
        • แอปมือถือ (Flutter) และ Chef Web Portal จะเชื่อมต่อมายังเอนด์พอยท์ในหน้านี้เพื่อแลกเปลี่ยนข้อมูลจริงโดยอัตโนมัติ
    </div>
</div>

</body>
</html>