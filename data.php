<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 使用绝对路径
$file = __DIR__ . "/data.json";

// 初始化文件
if (!file_exists($file)) {
    file_put_contents($file, json_encode([]));
}

// 读取数据
function loadData($file) {
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    return $data === null ? [] : $data;
}

// 保存数据
function saveData($file, $data) {
    // 检查文件可写
    if (file_exists($file) && !is_writable($file)) {
        http_response_code(500);
        echo json_encode(["error" => "文件不可写，请检查权限"]);
        return false;
    }
    
    // 检查目录可写
    if (!is_writable(dirname($file))) {
        http_response_code(500);
        echo json_encode(["error" => "目录不可写，请检查权限"]);
        return false;
    }

    $result = file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    if ($result === false) {
        http_response_code(500);
        echo json_encode(["error" => "写入文件失败"]);
        return false;
    }
    return true;
}

// 数据验证函数
function validateRecord($record) {
    if (!isset($record['type']) || !in_array($record['type'], ['income', 'expense'])) {
        return "无效的类型";
    }
    
    if (!isset($record['amount']) || !is_numeric($record['amount']) || $record['amount'] <= 0) {
        return "无效的金额";
    }
    
    if (!isset($record['category']) || empty($record['category'])) {
        return "分类不能为空";
    }
    
    if (!isset($record['date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $record['date'])) {
        return "无效的日期格式";
    }
    
    if (isset($record['description']) && strlen($record['description']) > 100) {
        return "备注过长";
    }
    
    return true;
}

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// 解析请求体
$input = file_get_contents("php://input");
$requestData = json_decode($input, true);

// 处理不同请求方法
switch ($method) {
    case "GET":
        // 获取所有记录
        $data = loadData($file);
        echo json_encode($data);
        break;
        
    case "POST":
        // 添加新记录
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["error" => "无效 JSON: " . json_last_error_msg()]);
            break;
        }
        
        // 数据验证
        $validation = validateRecord($requestData);
        if ($validation !== true) {
            http_response_code(400);
            echo json_encode(["error" => "数据验证失败: " . $validation]);
            break;
        }
        
        // 生成唯一ID（使用时间戳）
        $requestData['id'] = round(microtime(true) * 1000);
        
        $data = loadData($file);
        array_unshift($data, $requestData); // 新记录添加到开头
        
        if (saveData($file, $data)) {
            echo json_encode(["success" => true, "message" => "记录添加成功", "id" => $requestData['id']]);
        }
        break;
        
    case "PUT":
        // 更新记录
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["error" => "无效 JSON: " . json_last_error_msg()]);
            break;
        }
        
        if (!isset($requestData['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "缺少ID参数"]);
            break;
        }
        
        // 数据验证
        $validation = validateRecord($requestData);
        if ($validation !== true) {
            http_response_code(400);
            echo json_encode(["error" => "数据验证失败: " . $validation]);
            break;
        }
        
        $data = loadData($file);
        $found = false;
        
        foreach ($data as &$record) {
            if ($record['id'] == $requestData['id']) {
                $record = $requestData;
                $found = true;
                break;
            }
        }
        
        if ($found) {
            if (saveData($file, $data)) {
                echo json_encode(["success" => true, "message" => "更新成功"]);
            }
        } else {
            http_response_code(404);
            echo json_encode(["error" => "未找到要更新的记录"]);
        }
        break;
        
    case "DELETE":
        // 删除记录
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "缺少ID参数"]);
            break;
        }
        
        $id = $_GET['id'];
        $data = loadData($file);
        $newData = array_filter($data, function($record) use ($id) {
            return $record['id'] != $id;
        });
        
        if (count($data) !== count($newData)) {
            if (saveData($file, array_values($newData))) {
                echo json_encode(["success" => true, "message" => "删除成功"]);
            }
        } else {
            http_response_code(404);
            echo json_encode(["error" => "未找到要删除的记录"]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["error" => "不支持的请求方法"]);
        break;
}
?>