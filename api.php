<?php
/* ─────────────────────────────────────────────────────────────
   약재검색포털 — JSON API

     GET  api.php?action=list
     POST api.php  {action:"upsert", csrf, item:{…}}
     POST api.php  {action:"delete", csrf, id:"…"}
     POST api.php  {action:"bulk",   csrf, mode:"pos"|"ware", replace:bool, items:[…]}
     POST api.php  {action:"seed",   csrf, items:[…]}       DB가 비어 있을 때만

   응답  {ok:true, data:…}  /  {ok:false, msg:"…"}
   미인증은 HTTP 401 — 화면이 로그인으로 되돌아갑니다.
   ───────────────────────────────────────────────────────────── */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/* 화면에서 다루는 필드 (DB 컬럼과 1:1) */
const FIELDS = ['name','hanja','cat','qty','full','min','unit',
                'yeol','haeng','zone','wyeol','whaeng','note'];

function out(array $payload, int $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $msg, int $status = 400)
{
    out(['ok' => false, 'msg' => $msg], $status);
}

/* 짧은 id — 시각(36진) + 요청 내 일련번호 + 난수.
   일련번호가 없으면 같은 밀리초에 여러 건을 넣을 때 PK 충돌로
   트랜잭션 전체가 롤백될 수 있습니다(시드 315건이 대표적). */
function new_id(): string
{
    static $seq = 0;
    return base_convert((string)(int)(microtime(true) * 1000), 10, 36)
         . base_convert((string)($seq++), 10, 36)
         . '-' . bin2hex(random_bytes(3));
}

/* 들어온 항목을 DB에 넣을 형태로 정리 */
function clean(array $raw): array
{
    $o = [];
    foreach (FIELDS as $f) {
        $o[$f] = $raw[$f] ?? '';
    }
    $o['name'] = trim((string)$o['name']) !== '' ? mb_substr(trim((string)$o['name']), 0, 100) : '무명';
    $o['hanja'] = mb_substr(trim((string)$o['hanja']), 0, 100);
    $o['cat']   = mb_substr(trim((string)$o['cat']) ?: '기타', 0, 20);
    $o['unit']  = mb_substr(trim((string)$o['unit']) ?: '돈', 0, 10);
    $o['qty']   = max(0, (int)$o['qty']);
    $o['min']   = max(0, (int)$o['min']);
    $o['full']  = max($o['qty'], max(0, (int)$o['full']) ?: 50);
    foreach (['yeol','haeng','zone','wyeol','whaeng'] as $f) {
        $o[$f] = mb_substr(trim((string)$o[$f]), 0, 4);
    }
    $o['zone'] = mb_strtoupper($o['zone']);
    $o['note'] = trim((string)$o['note']);
    return $o;
}

/* 전체 목록 — 화면 state 배열과 같은 순서 */
function fetch_all(PDO $pdo): array
{
    $sql = 'SELECT h.id, h.name, h.hanja, h.cat, h.qty, h.`full`, h.`min`, h.unit,
                   h.yeol, h.haeng, h.zone, h.wyeol, h.whaeng, h.note
              FROM herb h
             ORDER BY h.sort_no ASC, h.id ASC';
    $rows = $pdo->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        $r['qty']  = (int)$r['qty'];
        $r['full'] = (int)$r['full'];
        $r['min']  = (int)$r['min'];
        $r['note'] = (string)($r['note'] ?? '');
    }
    return $rows;
}

function insert_stmt(PDO $pdo): PDOStatement
{
    return $pdo->prepare(
        'INSERT INTO herb
            (id, name, hanja, cat, qty, `full`, `min`, unit,
             yeol, haeng, zone, wyeol, whaeng, note, sort_no)
         VALUES
            (:id, :name, :hanja, :cat, :qty, :full, :min, :unit,
             :yeol, :haeng, :zone, :wyeol, :whaeng, :note, :sort_no)'
    );
}

/* ── 인증 ── */
if (!is_logged_in()) {
    fail('로그인이 필요합니다', 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $pdo = db();

    /* ── 조회 ── */
    if ($method === 'GET') {
        $action = (string)($_GET['action'] ?? 'list');
        if ($action !== 'list') {
            fail('알 수 없는 요청입니다: ' . $action);
        }
        out(['ok' => true, 'data' => fetch_all($pdo)]);
    }

    if ($method !== 'POST') {
        fail('허용되지 않는 메서드입니다', 405);
    }

    /* ── 쓰기 ── */
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) {
        fail('요청 형식이 올바르지 않습니다');
    }
    if (!csrf_valid($body['csrf'] ?? null)) {
        fail('보안 토큰이 만료되었습니다. 새로고침 후 다시 시도하세요', 401);
    }

    $action = (string)($body['action'] ?? '');

    /* 단건 추가 · 수정 */
    if ($action === 'upsert') {
        if (!is_array($body['item'] ?? null)) {
            fail('저장할 내용이 없습니다');
        }
        $item = clean($body['item']);
        $id   = trim((string)($body['item']['id'] ?? ''));

        $exists = false;
        if ($id !== '') {
            $q = $pdo->prepare('SELECT 1 FROM herb h WHERE h.id = :id');
            $q->execute([':id' => $id]);
            $exists = (bool)$q->fetchColumn();
        }

        if ($exists) {
            $sql = 'UPDATE herb h
                       SET h.name = :name, h.hanja = :hanja, h.cat = :cat,
                           h.qty = :qty, h.`full` = :full, h.`min` = :min, h.unit = :unit,
                           h.yeol = :yeol, h.haeng = :haeng,
                           h.zone = :zone, h.wyeol = :wyeol, h.whaeng = :whaeng,
                           h.note = :note
                     WHERE h.id = :id';
            $pdo->prepare($sql)->execute($item + [':id' => $id]);
        } else {
            /* 신규는 목록 맨 앞에 (화면의 unshift 와 같은 동작) */
            $min = (int)$pdo->query('SELECT COALESCE(MIN(h.sort_no), 0) FROM herb h')->fetchColumn();
            $id  = $id !== '' ? $id : new_id();
            insert_stmt($pdo)->execute($item + [':id' => $id, ':sort_no' => $min - 1]);
        }

        $q = $pdo->prepare(
            'SELECT h.id, h.name, h.hanja, h.cat, h.qty, h.`full`, h.`min`, h.unit,
                    h.yeol, h.haeng, h.zone, h.wyeol, h.whaeng, h.note
               FROM herb h WHERE h.id = :id'
        );
        $q->execute([':id' => $id]);
        $row = $q->fetch();
        if (!$row) {
            fail('저장한 내용을 다시 읽지 못했습니다', 500);
        }
        $row['qty']  = (int)$row['qty'];
        $row['full'] = (int)$row['full'];
        $row['min']  = (int)$row['min'];
        $row['note'] = (string)($row['note'] ?? '');
        out(['ok' => true, 'data' => $row]);
    }

    /* 단건 삭제 */
    if ($action === 'delete') {
        $id = trim((string)($body['id'] ?? ''));
        if ($id === '') {
            fail('삭제할 대상이 없습니다');
        }
        $st = $pdo->prepare('DELETE h FROM herb h WHERE h.id = :id');
        $st->execute([':id' => $id]);
        out(['ok' => true, 'data' => ['deleted' => $st->rowCount()]]);
    }

    /* 가져오기 — 약재위치(pos) · 약재창고(ware) */
    if ($action === 'bulk') {
        $mode    = (string)($body['mode'] ?? 'pos');
        $replace = !empty($body['replace']);
        $items   = is_array($body['items'] ?? null) ? $body['items'] : [];
        if (!$items) {
            fail('인식된 약재가 없습니다');
        }
        if ($mode !== 'pos' && $mode !== 'ware') {
            fail('알 수 없는 가져오기 방식입니다: ' . $mode);
        }

        $upd = 0;
        $add = 0;
        $pdo->beginTransaction();
        try {
            if ($mode === 'ware') {
                if ($replace) {
                    /* 기존 창고 위치를 모두 비운다 */
                    $pdo->exec("UPDATE herb h SET h.zone = '', h.wyeol = '', h.whaeng = ''");
                }
                $find = $pdo->prepare('SELECT h.id FROM herb h WHERE h.name = :name ORDER BY h.sort_no ASC LIMIT 1');
                $set  = $pdo->prepare(
                    'UPDATE herb h SET h.zone = :zone, h.wyeol = :wyeol, h.whaeng = :whaeng WHERE h.id = :id'
                );
                $ins  = insert_stmt($pdo);
                $next = (int)$pdo->query('SELECT COALESCE(MAX(h.sort_no), 0) FROM herb h')->fetchColumn();

                foreach ($items as $p) {
                    $name = trim((string)($p['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $find->execute([':name' => $name]);
                    $hit = $find->fetchColumn();
                    if ($hit) {
                        $set->execute([
                            ':zone'   => mb_strtoupper(mb_substr(trim((string)($p['zone'] ?? '')), 0, 4)),
                            ':wyeol'  => mb_substr(trim((string)($p['wyeol'] ?? '')), 0, 4),
                            ':whaeng' => mb_substr(trim((string)($p['whaeng'] ?? '')), 0, 4),
                            ':id'     => $hit,
                        ]);
                        $upd++;
                    } else {
                        $ins->execute(clean([
                            'name'   => $name,
                            'zone'   => $p['zone']   ?? '',
                            'wyeol'  => $p['wyeol']  ?? '',
                            'whaeng' => $p['whaeng'] ?? '',
                        ]) + [':id' => new_id(), ':sort_no' => ++$next]);
                        $add++;
                    }
                }
            } else {
                if ($replace) {
                    /* 기존 데이터를 전부 교체 */
                    $pdo->exec('DELETE FROM herb');
                    $ins  = insert_stmt($pdo);
                    $n    = 0;
                    foreach ($items as $p) {
                        $name = trim((string)($p['name'] ?? ''));
                        if ($name === '') {
                            continue;
                        }
                        $ins->execute(clean([
                            'name'  => $name,
                            'yeol'  => $p['yeol']  ?? '',
                            'haeng' => $p['haeng'] ?? '',
                        ]) + [':id' => new_id(), ':sort_no' => $n++]);
                        $add++;
                    }
                } else {
                    $find = $pdo->prepare('SELECT h.id FROM herb h WHERE h.name = :name ORDER BY h.sort_no ASC LIMIT 1');
                    $set  = $pdo->prepare('UPDATE herb h SET h.yeol = :yeol, h.haeng = :haeng WHERE h.id = :id');
                    $ins  = insert_stmt($pdo);
                    $next = (int)$pdo->query('SELECT COALESCE(MAX(h.sort_no), 0) FROM herb h')->fetchColumn();

                    foreach ($items as $p) {
                        $name = trim((string)($p['name'] ?? ''));
                        if ($name === '') {
                            continue;
                        }
                        $find->execute([':name' => $name]);
                        $hit = $find->fetchColumn();
                        if ($hit) {
                            $set->execute([
                                ':yeol'  => mb_substr(trim((string)($p['yeol'] ?? '')), 0, 4),
                                ':haeng' => mb_substr(trim((string)($p['haeng'] ?? '')), 0, 4),
                                ':id'    => $hit,
                            ]);
                            $upd++;
                        } else {
                            $ins->execute(clean([
                                'name'  => $name,
                                'yeol'  => $p['yeol']  ?? '',
                                'haeng' => $p['haeng'] ?? '',
                            ]) + [':id' => new_id(), ':sort_no' => ++$next]);
                            $add++;
                        }
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        out(['ok' => true, 'updated' => $upd, 'added' => $add, 'data' => fetch_all($pdo)]);
    }

    /* 초기 데이터 적재 — 비어 있을 때만 */
    if ($action === 'seed') {
        $items = is_array($body['items'] ?? null) ? $body['items'] : [];
        if (!$items) {
            fail('넣을 데이터가 없습니다');
        }
        if ((int)$pdo->query('SELECT COUNT(*) FROM herb h')->fetchColumn() > 0) {
            fail('이미 데이터가 있습니다. 초기 적재는 비어 있을 때만 가능합니다');
        }

        $pdo->beginTransaction();
        try {
            $ins = insert_stmt($pdo);
            $n   = 0;
            foreach ($items as $p) {
                if (!is_array($p) || trim((string)($p['name'] ?? '')) === '') {
                    continue;
                }
                $ins->execute(clean($p) + [':id' => new_id(), ':sort_no' => $n++]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        out(['ok' => true, 'added' => $n, 'data' => fetch_all($pdo)]);
    }

    fail('알 수 없는 요청입니다: ' . $action);

} catch (PDOException $e) {
    error_log('[herb-api] ' . $e->getMessage());
    fail('데이터베이스 오류: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    error_log('[herb-api] ' . $e->getMessage());
    fail('처리 중 오류가 발생했습니다: ' . $e->getMessage(), 500);
}
