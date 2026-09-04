/* ─────────────────────────────────────────────────────────────
   약재검색포털 — 테이블 생성 (1회 실행)
   카페24 뉴아우토반 / MariaDB 10.x / InnoDB / utf8mb4

   실행 방법
     - phpMyAdmin: SQL 탭에 붙여넣고 실행
     - SSH:  mysql -u {아이디} -p {DB명} < schema.sql

   실행 후 이 파일은 서버에서 삭제할 것.
   ───────────────────────────────────────────────────────────── */

CREATE TABLE IF NOT EXISTS herb (
  id         VARCHAR(24)  NOT NULL                COMMENT '화면에서 생성한 uid',
  name       VARCHAR(100) NOT NULL                COMMENT '약재명',
  hanja      VARCHAR(100) NOT NULL DEFAULT ''     COMMENT '한자명',
  cat        VARCHAR(20)  NOT NULL DEFAULT '기타' COMMENT '분류 (보기/보혈/보음/해표/청열/화담/이기/활혈/기타)',
  qty        INT          NOT NULL DEFAULT 0      COMMENT '현재 수량',
  `full`     INT          NOT NULL DEFAULT 50     COMMENT '가득 찼을 때 수량 (게이지 기준)',
  `min`      INT          NOT NULL DEFAULT 0      COMMENT '부족 경고 기준 수량',
  unit       VARCHAR(10)  NOT NULL DEFAULT '돈'   COMMENT '단위',
  yeol       VARCHAR(4)   NOT NULL DEFAULT ''     COMMENT '약장 열 (1~9, R)',
  haeng      VARCHAR(4)   NOT NULL DEFAULT ''     COMMENT '약장 행 (1~18)',
  zone       VARCHAR(4)   NOT NULL DEFAULT ''     COMMENT '창고 구역 (A~Z)',
  wyeol      VARCHAR(4)   NOT NULL DEFAULT ''     COMMENT '창고 열',
  whaeng     VARCHAR(4)   NOT NULL DEFAULT ''     COMMENT '창고 행',
  note       TEXT         NULL                    COMMENT '메모',
  sort_no    INT          NOT NULL DEFAULT 0      COMMENT '목록 정렬 순서 (작을수록 앞)',
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_herb_name (name),
  KEY ix_herb_pos  (yeol, haeng),
  KEY ix_herb_ware (zone, wyeol, whaeng)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='약재 재고 · 약장/창고 위치';
