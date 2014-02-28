# log_functions2html

PostgreSQLのPL/pgSQLのストアドプロシージャを実行した時のラインカバレッジを取得するためのスクリプトです。

[gleu / log_functions](https://github.com/gleu/log_functions)が出力するログを解析して、解析結果をCSVとHTMLで出力します。

# 実行方法

    $ export PGUSER=postgres
    $ export PGDATABASE=my_db
    $ psql -c "load 'log_functions'; set log_functions.log_statement_begin = true; begin; select my_func(1, 2, 3); rollback;"
    $ php log_functions2html.php my_func /var/lib/pgsql/data/pg_log/postgresql-Fri.log my_func.csv >my_func.html

※ pg_log下のログファイルへアクセス可能なパーミッションが実行ユーザに必要です
