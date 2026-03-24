<?php
// =========================================================
// 1. الجزء الخاص بالخادم (Backend Proxy)
// =========================================================

// نتحقق إذا كان الطلب قادم من الـ JavaScript (AJAX) للاستعلام
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['NID']) && isset($_POST['is_ajax'])) {
    $nid = $_POST['NID'];

    // إعداد الاتصال بموقع التأمين الصحي
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://wlms.smcegy.com/WLMSOnline/Online/InsuranceDetails");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'DecType' => '1',
        'IsTransfer' => 'false',
        'NID' => $nid
    ]));
    
    // إعدادات ضرورية لتخطي مشاكل شهادات الأمان (SSL)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // تنفيذ الطلب وجلب النتيجة
    $response = curl_exec($ch);

    if(curl_errno($ch)){
        echo "<div class='alert alert-danger'>خطأ في الاتصال بالسيرفر: " . curl_error($ch) . "</div>";
    } else {
        echo $response; // إرجاع صفحة الـ HTML الكاملة الخاصة بالوزارة
    }
    
    curl_close($ch);
    exit; // إيقاف تنفيذ باقي الصفحة لأننا نريد فقط إرسال الرد للـ JavaScript
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعلام موافقات التأمين الصحي</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .card-header { background-color: #01a6ff; color: white; font-weight: bold; border-radius: 10px 10px 0 0 !important; }
        .btn-search { background-color: #01a6ff; color: white; transition: 0.3s; }
        .btn-search:hover { background-color: #008ecc; color: white; }
        .table-responsive { margin-top: 20px; }
        table th { background-color: #e9ecef !important; text-align: center; white-space: nowrap; }
        table td { vertical-align: middle; text-align: center; }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="card">
        <div class="card-header py-3">
            <i class="fa fa-notes-medical ms-2"></i> موافقات التأمين الصحي
        </div>
        <div class="card-body p-4">
            <form id="searchForm" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="nidInput" class="form-label fw-bold">الرقم القومى <span class="text-danger">*</span></label>
                    <input type="number" class="form-control form-control-lg" id="nidInput" placeholder="أدخل الرقم القومي (14 رقم)" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-search btn-lg w-100" id="searchBtn">
                        بحث <i class="fa fa-search ms-1"></i>
                    </button>
                </div>
            </form>

            <div id="loadingSpinner" class="text-center mt-5 d-none">
                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
                <h5 class="mt-3 text-muted">جاري الاستعلام، يرجى الانتظار...</h5>
            </div>
            
            <div id="resultContainer" class="table-responsive d-none mt-4">
                </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('searchForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const nid = document.getElementById('nidInput').value;
        const resultContainer = document.getElementById('resultContainer');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const searchBtn = document.getElementById('searchBtn');
        
        if(nid.length !== 14) {
            alert("من فضلك أدخل رقم قومي صحيح مكون من 14 رقم.");
            return;
        }

        // إعداد الواجهة للاستعلام
        loadingSpinner.classList.remove('d-none');
        resultContainer.classList.add('d-none');
        resultContainer.innerHTML = '';
        searchBtn.disabled = true;

        // تجهيز البيانات لإرسالها لنفس الملف (Backend Proxy)
        const formData = new URLSearchParams();
        formData.append('NID', nid);
        formData.append('is_ajax', 'true'); // هذا المتغير ليخبر الـ PHP في الأعلى أن هذا طلب جلب بيانات

        try {
            // إرسال الطلب لنفس مسار الصفحة الحالية
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString()
            });

            const htmlText = await response.text();
            
            // تحويل النص المستلم إلى DOM للبحث داخله عن الجدول المستهدف
            const parser = new DOMParser();
            const doc = parser.parseFromString(htmlText, 'text/html');
            
            // استخراج الجدول الخاص بالقرارات من صفحة الوزارة
            const table = doc.getElementById('DecreeListTable');

            if(table) {
                // إضافة كلاسات Bootstrap للجدول ليظهر بشكل أنيق
                table.classList.add('table', 'table-bordered', 'table-striped', 'table-hover');
                resultContainer.innerHTML = table.outerHTML;
            } else {
                resultContainer.innerHTML = '<div class="alert alert-warning text-center"><i class="fa fa-exclamation-triangle ms-2"></i> لا توجد موافقات تأمين صحي مسجلة لهذا الرقم القومي.</div>';
            }

        } catch (error) {
            console.error('Error fetching data:', error);
            resultContainer.innerHTML = '<div class="alert alert-danger text-center"><i class="fa fa-times-circle ms-2"></i> حدث خطأ أثناء الاتصال بالخادم. يرجى المحاولة لاحقاً.</div>';
        } finally {
            // إعادة الواجهة لحالتها الطبيعية
            loadingSpinner.classList.add('d-none');
            resultContainer.classList.remove('d-none');
            searchBtn.disabled = false;
        }
    });
</script>

</body>
</html>