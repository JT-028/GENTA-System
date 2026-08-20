# GENTA Report API Endpoints

## Overview
Two new endpoints have been added to GENTA Flask server for retrieving student reports via the ngrok tunnel. These endpoints are protected with API key authentication.

## Authentication
**API Key:** `YOUR_GENTA_API_KEY`

### Methods (choose one):
1. **Header (Recommended):**
   ```
   X-GENTA-API-KEY: YOUR_GENTA_API_KEY
   ```

2. **Bearer Token:**
   ```
   Authorization: Bearer YOUR_GENTA_API_KEY
   ```

3. **Query Parameter (less secure):**
   ```
   ?api_key=YOUR_GENTA_API_KEY
   ```

## Endpoints

### 1. Analysis Report
**URL:** `https://nonbasic-bob-inimical.ngrok-free.dev/analysis_report`  
**Method:** GET

#### Parameters:
- `lrn` (string, required*): Student LRN (12 digits)
- `student_name` (string, optional): Student name
- `filename` (string, optional): Exact filename to download
- `format` (string, optional): 'file' (default) or 'json'

*Either `lrn` or `filename` must be provided

#### Response:
- **200 OK:** Returns .docx file (binary) or JSON metadata
- **400 Bad Request:** Missing required parameters
- **404 Not Found:** Report not found
- **401 Unauthorized:** Invalid/missing API key

#### Examples:

**Download file directly:**
```bash
curl -H "X-GENTA-API-KEY: YOUR_GENTA_API_KEY" \
     "https://nonbasic-bob-inimical.ngrok-free.dev/analysis_report?lrn=107048090462" \
     --output analysis_report.docx
```

**Get JSON metadata:**
```bash
curl -H "X-GENTA-API-KEY: YOUR_GENTA_API_KEY" \
     "https://nonbasic-bob-inimical.ngrok-free.dev/analysis_report?lrn=107048090462&format=json"
```

**Response (JSON format):**
```json
{
  "ok": true,
  "file_name": "analysis_result_Jonathan_Tiglao_107048090462.docx",
  "file_size": 45678,
  "download_url": "/analysis_report?filename=analysis_result_Jonathan_Tiglao_107048090462.docx",
  "lrn": "107048090462",
  "report_type": "analysis"
}
```

---

### 2. Tailored Module
**URL:** `https://nonbasic-bob-inimical.ngrok-free.dev/tailored_module`  
**Method:** GET

#### Parameters:
- `lrn` (string, required*): Student LRN (12 digits)
- `student_name` (string, optional): Student name
- `filename` (string, optional): Exact filename to download
- `format` (string, optional): 'file' (default) or 'json'

*Either `lrn` or `filename` must be provided

#### Response:
- **200 OK:** Returns .docx file (binary) or JSON metadata
- **400 Bad Request:** Missing required parameters
- **404 Not Found:** Report not found
- **401 Unauthorized:** Invalid/missing API key

#### Examples:

**Download file directly:**
```bash
curl -H "X-GENTA-API-KEY: YOUR_GENTA_API_KEY" \
     "https://nonbasic-bob-inimical.ngrok-free.dev/tailored_module?lrn=107048090462" \
     --output tailored_module.docx
```

**Get JSON metadata:**
```bash
curl -H "X-GENTA-API-KEY: YOUR_GENTA_API_KEY" \
     "https://nonbasic-bob-inimical.ngrok-free.dev/tailored_module?lrn=107048090462&format=json"
```

---

## CakePHP Integration Example

### Controller Code (CakePHP 4.x)

```php
<?php
namespace App\Controller;

use Cake\Http\Client;

class ReportsController extends AppController
{
    public function downloadAnalysisReport($studentLrn)
    {
        $http = new Client();
        
        $response = $http->get('https://nonbasic-bob-inimical.ngrok-free.dev/analysis_report', 
            ['lrn' => $studentLrn],
            ['headers' => ['X-GENTA-API-KEY' => 'YOUR_GENTA_API_KEY']]
        );
        
        if ($response->isOk()) {
            // Save file or return to browser
            $filename = "analysis_{$studentLrn}.docx";
            $this->response = $this->response->withStringBody($response->getStringBody());
            $this->response = $this->response->withType('docx');
            $this->response = $this->response->withDownload($filename);
            return $this->response;
        } else {
            $this->Flash->error('Report not found');
            return $this->redirect(['action' => 'index']);
        }
    }
    
    public function downloadTailoredModule($studentLrn)
    {
        $http = new Client();
        
        $response = $http->get('https://nonbasic-bob-inimical.ngrok-free.dev/tailored_module', 
            ['lrn' => $studentLrn],
            ['headers' => ['X-GENTA-API-KEY' => 'YOUR_GENTA_API_KEY']]
        );
        
        if ($response->isOk()) {
            $filename = "tailored_module_{$studentLrn}.docx";
            $this->response = $this->response->withStringBody($response->getStringBody());
            $this->response = $this->response->withType('docx');
            $this->response = $this->response->withDownload($filename);
            return $this->response;
        } else {
            $this->Flash->error('Module not found');
            return $this->redirect(['action' => 'index']);
        }
    }
}
```

### Route Configuration (routes.php)
```php
$routes->connect('/reports/analysis/:lrn', ['controller' => 'Reports', 'action' => 'downloadAnalysisReport'])
    ->setPass(['lrn']);
    
$routes->connect('/reports/module/:lrn', ['controller' => 'Reports', 'action' => 'downloadTailoredModule'])
    ->setPass(['lrn']);
```

---

## Security Notes

1. **Keep API Key Secure:** Store in environment variable or `app_local.php` (not in version control)
2. **HTTPS Only:** ngrok tunnel uses HTTPS by default
3. **IP Whitelisting:** Consider restricting access to your CakePHP server IP
4. **Key Rotation:** Change the API key periodically and update both sides

---

## Testing Checklist

- [ ] Test with valid LRN
- [ ] Test with invalid LRN (should return 404)
- [ ] Test without API key (should return 401)
- [ ] Test with wrong API key (should return 401)
- [ ] Test JSON format response
- [ ] Test file download
- [ ] Test from CakePHP controller
- [ ] Verify file integrity after download

---

## Troubleshooting

**401 Unauthorized:**
- Check API key is correct
- Verify header name: `X-GENTA-API-KEY`
- Ensure no extra spaces in key value

**404 Not Found:**
- Verify student has completed quiz (reports exist)
- Check LRN format (12 digits)
- Check UPLOAD_DIR contains the files

**Connection Error:**
- Verify ngrok tunnel is running
- Check Flask server is running on port 5000
- Test local endpoint first: `http://localhost:5000/analysis_report?lrn=...`

---

## Environment Variables

Set these on the Flask server (local or deployed):

```bash
# Windows PowerShell
$env:GENTA_REPORT_UPLOAD_API_KEY='YOUR_GENTA_API_KEY'

# Windows CMD (persistent)
setx GENTA_REPORT_UPLOAD_API_KEY "YOUR_GENTA_API_KEY"

# Linux/Mac
export GENTA_REPORT_UPLOAD_API_KEY='YOUR_GENTA_API_KEY'
```
