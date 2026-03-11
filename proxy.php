<?php
header('Content-Type: application/json');

// Allow requests from any origin
header('Access-Control-Allow-Origin: *');
// Allow specific methods
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
// Allow specific headers
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if (!isset($_POST['national_id']) || empty(trim($_POST['national_id']))) {
    echo json_encode(['success' => false, 'error' => 'الرقم القومي مفقود.']);
    exit;
}

$nationalId = trim($_POST['national_id']);

function searchByNationalId($nationalId) {
    $postData = [
        'NID' => $nationalId,
        'DecType' => '1',
        'IsTransfer' => 'false'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://wlms.smcegy.com/WLMSOnline/Online/InsuranceDetails');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_REFERER, 'https://wlms.smcegy.com/WLMSOnline/Online/InsuranceDetails');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error = 'خطأ في الاتصال: ' . curl_error($ch);
        curl_close($ch);
        return ['error' => $error];
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['error' => 'خطأ في الاستجابة من الخادم: ' . $httpCode];
    }

    return $response;
}

function parseResults($html) {
    $dom = new DOMDocument();
    @$dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html);
    
    $results = [];
    $tbody = $dom->getElementById('InsuranceTbodyTable');
    
    if ($tbody) {
        $rows = $tbody->getElementsByTagName('tr');
        $headers = [
            'رقم القرار',
            'الرقم القومي للمريض', 
            'اسم المريض',
            'رقم التليفون',
            'المستشفى',
            'المستشفى الموجه لها',
            'التشخيص',
            'الإجراء',
            'تاريخ القرار',
            'حالة الاجراء'
        ];
        
        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('td');
            $data = [];
            
            if ($cells->length >= 10) {
                foreach ($cells as $index => $cell) {
                    if (isset($headers[$index])) {
                        $data[$headers[$index]] = trim($cell->nodeValue);
                    }
                }
            }
            
            if (!empty($data)) {
                $results[] = $data;
            }
        }
    }
    
    return $results;
}

$response = searchByNationalId($nationalId);

if (isset($response['error'])) {
    echo json_encode(['success' => false, 'error' => $response['error']]);
    exit;
}

$results = parseResults($response);

if (empty($results)) {
    $error_msg = 'لا توجد موافقات تأمين صحي مسجلة لهذا الرقم القومي أو البيانات غير متاحة حالياً.';
    echo json_encode(['success' => false, 'error' => $error_msg]);
    exit;
}

echo json_encode(['success' => true, 'approvals' => $results]);
?>
