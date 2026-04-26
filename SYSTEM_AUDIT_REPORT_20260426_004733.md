# Comprehensive System Audit Report
Generated: Sun Apr 26 00:47:33 CET 2026

## 1. System Overview
```
 00:47:33 up 20 days,  2:13,  3 users,  load average: 6.24, 7.39, 6.28

CPU Info:
CPU(s):              8
Thread(s) per core:  2
Core(s) per socket:  4
Socket(s):           1
Model name:          Intel(R) Xeon(R) CPU E3-1240 v3 @ 3.40GHz
BIOS Model name:     Intel(R) Xeon(R) CPU E3-1240 v3 @ 3.40GHz

Memory:
              total        used        free      shared  buff/cache   available
Mem:           31Gi        16Gi       4.7Gi       883Mi       9.7Gi        13Gi
Swap:         5.9Gi       856Mi       5.0Gi

Disk Usage:
Filesystem      Size  Used Avail Use% Mounted on
/dev/sda2       1.8T  302G  1.4T  18% /
```

## 2. Current Load Analysis (2-minute sample)
Collecting 24 samples at 5-second intervals...
```
Sample 1 at 00:47:33:
Load: 6.24, 7.39, 6.28
USER        %CPU  %MEM COMMAND
elastic+    35.9  27.3 /usr/share/elasticsearch/jdk/bin/java
root        15.1   0.8 /root/.cache/ms-playwright/chromium_headless_shell-1217/chrome-headless-shell-linux64/chrome-headless-shell
technad+    14.2   0.3 php-fpm:
root         9.1   0.0 find
pim          7.8   0.2 php-fpm:

Sample 2 at 00:47:38:
Load: 5.90, 7.30, 6.26
USER        %CPU  %MEM COMMAND
elastic+    35.0  27.3 /usr/share/elasticsearch/jdk/bin/java
technad+    13.9   0.3 php-fpm:
root         9.2   0.0 find
pim          5.9   0.2 php-fpm:
root         5.6   1.4 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/extensions/aicoding-agent/bin/x86_64_linux/Qoder

Sample 3 at 00:47:43:
Load: 5.58, 7.21, 6.23
USER        %CPU  %MEM COMMAND
elastic+    34.1  27.3 /usr/share/elasticsearch/jdk/bin/java
technad+    13.5   0.3 php-fpm:
root         9.3   0.0 find
root         5.6   1.4 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/extensions/aicoding-agent/bin/x86_64_linux/Qoder
root         5.0   3.5 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node

Sample 4 at 00:47:48:
Load: 5.30, 7.12, 6.21
USER        %CPU  %MEM COMMAND
elastic+    33.2  27.3 /usr/share/elasticsearch/jdk/bin/java
technad+    13.2   0.3 php-fpm:
root         9.4   0.0 find
root         5.6   1.4 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/extensions/aicoding-agent/bin/x86_64_linux/Qoder
beta         5.3   0.5 php-fpm:

Sample 5 at 00:47:53:
Load: 5.03, 7.04, 6.19
USER        %CPU  %MEM COMMAND
elastic+    32.4  27.3 /usr/share/elasticsearch/jdk/bin/java
technad+    12.9   0.3 php-fpm:
root         9.5   0.0 find
root         5.6   1.4 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/extensions/aicoding-agent/bin/x86_64_linux/Qoder
root         5.0   3.5 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node

Sample 6 at 00:47:58:
Load: 4.87, 6.97, 6.17
USER        %CPU  %MEM COMMAND
elastic+    31.7  27.3 /usr/share/elasticsearch/jdk/bin/java
technad+    12.6   0.3 php-fpm:
root         9.6   0.0 find
root         5.6   1.4 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/extensions/aicoding-agent/bin/x86_64_linux/Qoder
root         5.0   3.5 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node

Sample 7 at 00:48:03:
Load: 4.80, 6.92, 6.16
USER        %CPU  %MEM COMMAND
elastic+    30.9  27.3 /usr/share/elasticsearch/jdk/bin/java
technad+    12.4   0.3 php-fpm:
root         9.7   0.0 find
root         5.6   1.4 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/extensions/aicoding-agent/bin/x86_64_linux/Qoder
root         5.3   0.3 qodercli

Sample 8 at 00:48:08:
Load: 4.82, 6.89, 6.15
USER        %CPU  %MEM COMMAND
elastic+    30.2  27.3 /usr/share/elasticsearch/jdk/bin/java
technad+    12.1   0.3 php-fpm:
root         9.8   0.0 find
root         6.2   0.2 qodercli
root         5.6   1.4 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/extensions/aicoding-agent/bin/x86_64_linux/Qoder

Sample 9 at 00:48:13:
Load: 4.59, 6.81, 6.13
USER        %CPU  %MEM COMMAND
elastic+    29.6  27.3 /usr/share/elasticsearch/jdk/bin/java
technad+    11.9   0.3 php-fpm:
root         9.9   0.0 find
root         6.8   0.2 qodercli
root         5.6   1.4 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/extensions/aicoding-agent/bin/x86_64_linux/Qoder

Sample 10 at 00:48:18:
Load: 4.54, 6.76, 6.12
USER        %CPU  %MEM COMMAND
root        92.0   0.4 webpack
dashboa+    34.0   0.0 du
elastic+    29.0  27.3 /usr/share/elasticsearch/jdk/bin/java
technad+    11.6   0.3 php-fpm:
root        11.0   0.2 node

Sample 11 at 00:48:23:
Load: 4.42, 6.70, 6.10
USER        %CPU  %MEM COMMAND
root         109   0.8 webpack
elastic+    28.4  27.3 /usr/share/elasticsearch/jdk/bin/java
technad+    11.4   0.3 php-fpm:
root        10.0   0.0 find
root         7.9   0.2 qodercli

Sample 12 at 00:48:28:
Load: 4.39, 6.65, 6.09
USER        %CPU  %MEM COMMAND
root         101   0.9 webpack
root        51.7   0.4 /usr/bin/node
root        50.5   0.4 /usr/bin/node
root        48.7   0.4 /usr/bin/node
root        48.0   0.4 /usr/bin/node

Sample 13 at 00:48:33:
Load: 4.27, 6.59, 6.07
USER        %CPU  %MEM COMMAND
root         107   1.0 webpack
elastic+    27.3  27.3 /usr/share/elasticsearch/jdk/bin/java
technad+    11.1   0.3 php-fpm:
root        10.1   0.0 find
beta         9.0   0.5 php-fpm:

Sample 14 at 00:48:38:
Load: 4.41, 6.58, 6.07
USER        %CPU  %MEM COMMAND
root         115   1.1 webpack
elastic+    26.8  27.3 /usr/share/elasticsearch/jdk/bin/java
beta        11.2   0.4 php-fpm:
technad+    10.9   0.3 php-fpm:
root        10.2   0.0 find

Sample 15 at 00:48:43:
Load: 4.38, 6.54, 6.06
USER        %CPU  %MEM COMMAND
root         119   1.2 webpack
elastic+    26.2  27.3 /usr/share/elasticsearch/jdk/bin/java
technad+    10.8   0.3 php-fpm:
root        10.2   0.0 find
root         9.5   0.2 qodercli

Sample 16 at 00:48:48:
Load: 4.43, 6.51, 6.05
USER        %CPU  %MEM COMMAND
root         124   1.3 webpack
root        92.0   0.2 /opt/cpanel/ea-php82/root/usr/bin/php
elastic+    25.7  27.3 /usr/share/elasticsearch/jdk/bin/java
technad+    10.7   0.3 php-fpm:
root        10.3   0.0 find

Sample 17 at 00:48:53:
Load: 4.56, 6.50, 6.05
USER        %CPU  %MEM COMMAND
root         127   1.7 webpack
elastic+    25.3  27.3 /usr/share/elasticsearch/jdk/bin/java
technad+    10.4   0.3 php-fpm:
root        10.3   0.0 find
root        10.1   0.2 qodercli

Sample 18 at 00:48:59:
Load: 4.51, 6.46, 6.04
USER        %CPU  %MEM COMMAND
root         128   2.0 webpack
elastic+    24.8  27.3 /usr/share/elasticsearch/jdk/bin/java
root        10.4   0.0 find
root        10.4   0.2 qodercli
technad+    10.3   0.3 php-fpm:

Sample 19 at 00:49:04:
Load: 4.55, 6.44, 6.04
USER        %CPU  %MEM COMMAND
root         129   2.3 webpack
elastic+    24.3  27.3 /usr/share/elasticsearch/jdk/bin/java
root        10.5   0.0 find
root        10.5   0.2 qodercli
technad+    10.0   0.3 php-fpm:

Sample 20 at 00:49:09:
Load: 4.75, 6.45, 6.04
USER        %CPU  %MEM COMMAND
root         131   2.5 webpack
root        51.0   0.0 find
elastic+    23.8  27.3 /usr/share/elasticsearch/jdk/bin/java
root        10.7   0.2 qodercli
root        10.5   0.0 find

Sample 21 at 00:49:14:
Load: 5.09, 6.49, 6.06
USER        %CPU  %MEM COMMAND
root         160   1.7 /usr/bin/node
root         153   0.9 /usr/bin/node
root         120   2.6 webpack
elastic+    23.4  27.3 /usr/share/elasticsearch/jdk/bin/java
root        10.8   0.2 qodercli

Sample 22 at 00:49:19:
Load: 5.16, 6.48, 6.06
USER        %CPU  %MEM COMMAND
root         158   2.0 /usr/bin/node
root         130   1.3 /usr/bin/node
root         110   2.6 webpack
dashboa+    40.5   0.0 du
elastic+    23.1  27.3 /usr/share/elasticsearch/jdk/bin/java

Sample 23 at 00:49:24:
Load: 5.15, 6.45, 6.05
USER        %CPU  %MEM COMMAND
root         149   2.3 /usr/bin/node
root         102   2.6 webpack
dashboa+    94.3   0.3 /opt/cpanel/ea-php82/root/usr/bin/php
root        90.0   1.3 /usr/bin/node
elastic+    22.7  27.3 /usr/share/elasticsearch/jdk/bin/java

Sample 24 at 00:49:29:
Load: 5.22, 6.45, 6.05
USER        %CPU  %MEM COMMAND
root         143   0.0 [node]
root        95.6   2.6 webpack
root        68.7   0.0 [node]
elastic+    22.3  27.3 /usr/share/elasticsearch/jdk/bin/java
root        11.2   0.2 qodercli

```

## 3. Top Resource Consumers
### CPU Top 20
```
USER         PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND
elastic+ 1605243 22.0 27.4 14520908 8936036 ?    Ssl  00:44   1:07 /usr/share/elasticsearch/jdk/bin/java -Xshare:auto -Des.networkaddress.cache.ttl=60 -Des.networkaddress.cache.negative.ttl=10 -XX:+AlwaysPreTouch -Xss1m -Djava.awt.headless=true -Dfile.encoding=UTF-8 -Djna.nosys=true -XX:-OmitStackTraceInFastThrow -XX:+ShowCodeDetailsInExceptionMessages -Dio.netty.noUnsafe=true -Dio.netty.noKeySetOptimization=true -Dio.netty.recycler.maxCapacityPerThread=0 -Dio.netty.allocator.numDirectArenas=0 -Dlog4j.shutdownHookEnabled=false -Dlog4j2.disable.jmx=true -Dlog4j2.formatMsgNoLookups=true -Djava.locale.providers=SPI,COMPAT --add-opens=java.base/java.io=ALL-UNNAMED -Djava.security.manager=allow -XX:+UseG1GC -Djava.io.tmpdir=/var/lib/elasticsearch/tmp -XX:+HeapDumpOnOutOfMemoryError -XX:+ExitOnOutOfMemoryError -XX:HeapDumpPath=/var/lib/elasticsearch -XX:ErrorFile=/var/log/elasticsearch/hs_err_pid%p.log -Xlog:gc*,gc+age=trace,safepoint:file=/var/log/elasticsearch/gc.log:utctime,pid,tags:filecount=32,filesize=64m -XX:+UnlockDiagnosticVMOptions -XX:G1NumCollectionsKeepPinned=10000000 -Xms8g -Xmx8g -Djava.io.tmpdir=/var/lib/elasticsearch/tmp -XX:MaxDirectMemorySize=4294967296 -XX:InitiatingHeapOccupancyPercent=30 -XX:G1ReservePercent=25 -Des.path.home=/usr/share/elasticsearch -Des.path.conf=/etc/elasticsearch -Des.distribution.flavor=default -Des.distribution.type=rpm -Des.bundled_jdk=true -cp /usr/share/elasticsearch/lib/* org.elasticsearch.bootstrap.Elasticsearch
root     1763858 11.1  0.2 1341864 83856 pts/6   Sl+  00:47   0:14 qodercli
root     1580345 10.7  0.0  20736  3060 pts/5    S+   00:43   0:36 find pub/media -type f -exec chmod 644 {} ;
technad+ 1609192  9.2  0.3 1001316 110152 ?      S    00:44   0:27 php-fpm: pool technostationery_com
beta     1862506  6.5  0.6 1012660 198548 ?      S    00:48   0:04 php-fpm: pool beta_technostationery_com
root     1240240  5.6  1.3 6605480 435548 ?      Sl   Apr25  25:13 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/extensions/aicoding-agent/bin/x86_64_linux/Qoder start --workDir /root/.config/Qoder/0b7e365a4ba46f1308f3fa7aa2649c45759b360f3a7a8ba87f8731bb03cfca8b/SharedClientCache
root     1240324  5.4  3.5 2403916 1154948 ?     Sl   Apr25  24:08 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/out/bootstrap-fork --type=fileWatcher
root     1946013  4.0  0.0      0     0 ?        Z    00:49   0:00 [/opt/saltstack/] <defunct>
root     1240187  3.3  2.9 66837664 958636 ?     Sl   Apr25  14:59 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node --dns-result-order=ipv4first /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/out/bootstrap-fork --type=extensionHost --transformURIs --useHostProxy=false
technad+ 1644654  3.2  0.2 999360 81884 ?        S    00:45   0:07 php-fpm: pool technostationery_com
root     1452782  3.1  0.0  18388  6180 pts/0    S+   00:32   0:32 htop
root     1240084  2.2  2.8 56373700 941504 ?     Sl   Apr25   9:53 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node --dns-result-order=ipv4first /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/out/bootstrap-fork --type=extensionHost --transformURIs --useHostProxy=false
mysql    1606545  1.0  4.1 5889472 1363904 ?     Sl   00:44   0:03 /opt/mariadb10.6/mariadb/bin/mariadbd --defaults-file=/opt/mariadb10.6/my.cnf --basedir=/opt/mariadb10.6/mariadb --datadir=/opt/mariadb10.6/data --plugin-dir=/opt/mariadb10.6/mariadb/lib/plugin --log-error=/opt/mariadb10.6/mariadb-error.log --open-files-limit=20000 --pid-file=/opt/mariadb10.6/mariadb.pid --socket=/opt/mariadb10.6/mariadb.sock --port=3307
root     1240363  0.8  0.8 74798564 279252 ?     Ssl  Apr25   3:53 /root/.qoder-server/extensions/kilocode.kilo-code-7.2.22-linux-x64/bin/kilo serve --port 0
root     1240846  0.8  0.8 74622396 287588 ?     Ssl  Apr25   3:47 /root/.qoder-server/extensions/kilocode.kilo-code-7.2.22-linux-x64/bin/kilo serve --port 0
root     1266317  0.8  0.0 1342120 25980 pts/1   Sl+  Apr25   3:19 qodercli
root     1240202  0.5  0.3 1336384 119932 ?      Sl   Apr25   2:31 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/out/bootstrap-fork --type=fileWatcher
root     1397315  0.4  0.0 1274816 19172 pts/4   Sl+  Apr25   0:30 qodercli
redis    1608029  0.4  0.1 134308 59032 ?        Ssl  00:44   0:01 /usr/bin/redis-server 127.0.0.1:6379
imh-nod+    1072  0.3  0.0 2220284 28992 ?       Ssl  Apr05  86:53 /usr/local/bin/imh-node-exporter
```

### Memory Top 20
```
USER         PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND
elastic+ 1605243 22.0 27.4 14520908 8936036 ?    Ssl  00:44   1:07 /usr/share/elasticsearch/jdk/bin/java -Xshare:auto -Des.networkaddress.cache.ttl=60 -Des.networkaddress.cache.negative.ttl=10 -XX:+AlwaysPreTouch -Xss1m -Djava.awt.headless=true -Dfile.encoding=UTF-8 -Djna.nosys=true -XX:-OmitStackTraceInFastThrow -XX:+ShowCodeDetailsInExceptionMessages -Dio.netty.noUnsafe=true -Dio.netty.noKeySetOptimization=true -Dio.netty.recycler.maxCapacityPerThread=0 -Dio.netty.allocator.numDirectArenas=0 -Dlog4j.shutdownHookEnabled=false -Dlog4j2.disable.jmx=true -Dlog4j2.formatMsgNoLookups=true -Djava.locale.providers=SPI,COMPAT --add-opens=java.base/java.io=ALL-UNNAMED -Djava.security.manager=allow -XX:+UseG1GC -Djava.io.tmpdir=/var/lib/elasticsearch/tmp -XX:+HeapDumpOnOutOfMemoryError -XX:+ExitOnOutOfMemoryError -XX:HeapDumpPath=/var/lib/elasticsearch -XX:ErrorFile=/var/log/elasticsearch/hs_err_pid%p.log -Xlog:gc*,gc+age=trace,safepoint:file=/var/log/elasticsearch/gc.log:utctime,pid,tags:filecount=32,filesize=64m -XX:+UnlockDiagnosticVMOptions -XX:G1NumCollectionsKeepPinned=10000000 -Xms8g -Xmx8g -Djava.io.tmpdir=/var/lib/elasticsearch/tmp -XX:MaxDirectMemorySize=4294967296 -XX:InitiatingHeapOccupancyPercent=30 -XX:G1ReservePercent=25 -Des.path.home=/usr/share/elasticsearch -Des.path.conf=/etc/elasticsearch -Des.distribution.flavor=default -Des.distribution.type=rpm -Des.bundled_jdk=true -cp /usr/share/elasticsearch/lib/* org.elasticsearch.bootstrap.Elasticsearch
mysql    1606545  1.0  4.1 5889472 1363904 ?     Sl   00:44   0:03 /opt/mariadb10.6/mariadb/bin/mariadbd --defaults-file=/opt/mariadb10.6/my.cnf --basedir=/opt/mariadb10.6/mariadb --datadir=/opt/mariadb10.6/data --plugin-dir=/opt/mariadb10.6/mariadb/lib/plugin --log-error=/opt/mariadb10.6/mariadb-error.log --open-files-limit=20000 --pid-file=/opt/mariadb10.6/mariadb.pid --socket=/opt/mariadb10.6/mariadb.sock --port=3307
root     1240324  5.4  3.5 2403916 1154948 ?     Sl   Apr25  24:08 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/out/bootstrap-fork --type=fileWatcher
root     1240187  3.3  2.9 66837664 958636 ?     Sl   Apr25  14:59 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node --dns-result-order=ipv4first /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/out/bootstrap-fork --type=extensionHost --transformURIs --useHostProxy=false
root     1240084  2.2  2.8 56373700 941504 ?     Sl   Apr25   9:53 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node --dns-result-order=ipv4first /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/out/bootstrap-fork --type=extensionHost --transformURIs --useHostProxy=false
root     1240240  5.6  1.3 6605480 435548 ?      Sl   Apr25  25:13 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/extensions/aicoding-agent/bin/x86_64_linux/Qoder start --workDir /root/.config/Qoder/0b7e365a4ba46f1308f3fa7aa2649c45759b360f3a7a8ba87f8731bb03cfca8b/SharedClientCache
root     1240846  0.8  0.8 74622396 287588 ?     Ssl  Apr25   3:47 /root/.qoder-server/extensions/kilocode.kilo-code-7.2.22-linux-x64/bin/kilo serve --port 0
root     1240363  0.8  0.8 74798564 279252 ?     Ssl  Apr25   3:53 /root/.qoder-server/extensions/kilocode.kilo-code-7.2.22-linux-x64/bin/kilo serve --port 0
root     2735351  0.0  0.7 393176 248280 ?       Ss   Apr11   3:20 /usr/lib/systemd/systemd-journald
root     1284748  0.0  0.7 1201200 236988 ?      Sl   Apr25   0:08 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node --max-old-space-size=3072 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/extensions/node_modules/typescript/lib/tsserver.js --useInferredProjectPerProjectRoot --enableTelemetry --cancellationPipeName /tmp/vscode-typescript0/95b534d6c0141c8144cc/tscancellation-f90fc3f41bc6b0e4d637.tmp* --globalPlugins @vscode/copilot-typescript-server-plugin --pluginProbeLocations /root/.qoder-server/extensions/github.copilot-chat-0.33.5 --locale en --noGetErrOnBackgroundUpdate --canUseWatchEvents --validateDefaultNpmLocation --useNodeIpc
beta     1862506  6.5  0.6 1012660 198548 ?      S    00:48   0:04 php-fpm: pool beta_technostationery_com
root     1240967  0.0  0.5 43378868 178520 ?     Sl   Apr25   0:02 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node /root/.qoder-server/extensions/qwenlm.qwen-code-vscode-ide-companion-0.15.2-linux-x64/dist/qwen-cli/cli.js --acp --channel=VSCode
root     1313358  0.0  0.4 317172 150524 ?       S    Apr25   0:07 spamd child
root     1240805  0.0  0.4 43105664 139136 ?     Sl   Apr25   0:01 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node /root/.qoder-server/extensions/qwenlm.qwen-code-vscode-ide-companion-0.15.2-linux-x64/dist/qwen-cli/cli.js --acp --channel=VSCode
root     4159857  0.0  0.3 317076 123756 ?       S    Apr14   0:04 spamd child
root     1240202  0.5  0.3 1336384 119932 ?      Sl   Apr25   2:31 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/out/bootstrap-fork --type=fileWatcher
technad+ 1609192  9.2  0.3 1001316 110152 ?      S    00:44   0:27 php-fpm: pool technostationery_com
root     1284742  0.0  0.3 1082228 101724 ?      Sl   Apr25   0:02 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/node --max-old-space-size=3072 /root/.qoder-server/bin/43b08de523101082fc403d923ed12db5631cfe80/extensions/node_modules/typescript/lib/tsserver.js --serverMode partialSemantic --useInferredProjectPerProjectRoot --disableAutomaticTypingAcquisition --cancellationPipeName /tmp/vscode-typescript0/95b534d6c0141c8144cc/tscancellation-8c328ed2206b0e3e86ee.tmp* --globalPlugins @vscode/copilot-typescript-server-plugin --pluginProbeLocations /root/.qoder-server/extensions/github.copilot-chat-0.33.5 --locale en --noGetErrOnBackgroundUpdate --canUseWatchEvents --validateDefaultNpmLocation --useNodeIpc
root     1763858 11.1  0.2 1341864 83856 pts/6   Sl+  00:47   0:14 qodercli
technad+ 1644654  3.2  0.2 999360 81884 ?        S    00:45   0:07 php-fpm: pool technostationery_com
```

## 4. Service Status Check
```
● mariadb10.6.service - MariaDB 10.6 (Manual Build)
   Loaded: loaded (/etc/systemd/system/mariadb10.6.service; enabled; vendor preset: disabled)
   Active: active (running) since Sun 2026-04-26 00:44:29 CET; 5min ago
 Main PID: 1606058 (mysqld_safe)
    Tasks: 17 (limit: 203647)
   Memory: 1.3G
   CGroup: /system.slice/mariadb10.6.service
           ├─1606058 /bin/sh /opt/mariadb10.6/mariadb/bin/mysqld_safe --defaults-file=/opt/mariadb10.6/my.cnf
           └─1606545 /opt/mariadb10.6/mariadb/bin/mariadbd --defaults-file=/opt/mariadb10.6/my.cnf --basedir=/opt/mariadb10.6/mariadb --datadir=/opt/mariadb10.6/data --plugin-dir=/opt/mariadb10.6/mariadb/lib/plugin --log-error=/opt/mariadb10.6/mariadb-error.log --open-files-limit=20000 --pid-file=/opt/mariadb10.6/mariadb.pid --socket=/opt/mariadb10.6/mariadb.sock --port=3307

Apr 26 00:44:29 ded701.inmotionhosting.com systemd[1]: Started MariaDB 10.6 (Manual Build).
Apr 26 00:44:29 ded701.inmotionhosting.com mysqld_safe[1606058]: 260426 00:44:29 mysqld_safe Logging to '/opt/mariadb10.6/mariadb-error.log'.
Apr 26 00:44:29 ded701.inmotionhosting.com mysqld_safe[1606058]: 260426 00:44:29 mysqld_safe Starting mariadbd daemon with databases from /opt/mariadb10.6/data

● ea-php82-php-fpm.service - The PHP FastCGI Process Manager
   Loaded: loaded (/usr/lib/systemd/system/ea-php82-php-fpm.service; enabled; vendor preset: disabled)
   Active: active (running) since Sun 2026-04-26 00:44:28 CET; 5min ago
 Main PID: 1604031 (php-fpm)
   Status: "Processes active: 0, idle: 7, Requests: 172, slow: 0, Traffic: 0.80req/sec"
    Tasks: 8 (limit: 203647)
   Memory: 1.2G
   CGroup: /system.slice/ea-php82-php-fpm.service
           ├─1604031 php-fpm: master process (/opt/cpanel/ea-php82/root/etc/php-fpm.conf)
           ├─1609192 php-fpm: pool technostationery_com
           ├─1644654 php-fpm: pool technostationery_com
           ├─1862506 php-fpm: pool beta_technostationery_com
           ├─1921281 php-fpm: pool dashboard_technostationery_com
           ├─1921299 php-fpm: pool dashboard_technostationery_com
           ├─1921351 php-fpm: pool dashboard_technostationery_com

● redis.service - Redis persistent key-value database
   Loaded: loaded (/usr/lib/systemd/system/redis.service; enabled; vendor preset: disabled)
  Drop-In: /etc/systemd/system/redis.service.d
           └─limit.conf
   Active: active (running) since Sun 2026-04-26 00:44:32 CET; 5min ago
  Process: 1607955 ExecStop=/usr/libexec/redis-shutdown (code=exited, status=0/SUCCESS)
 Main PID: 1608029 (redis-server)
    Tasks: 4 (limit: 203647)
   Memory: 61.3M
   CGroup: /system.slice/redis.service
           └─1608029 /usr/bin/redis-server 127.0.0.1:6379

Apr 26 00:44:32 ded701.inmotionhosting.com systemd[1]: redis.service: Succeeded.
Apr 26 00:44:32 ded701.inmotionhosting.com systemd[1]: Stopped Redis persistent key-value database.
Apr 26 00:44:32 ded701.inmotionhosting.com systemd[1]: Starting Redis persistent key-value database...

● varnish.service - Varnish Cache, a high-performance HTTP accelerator
   Loaded: loaded (/etc/systemd/system/varnish.service; enabled; vendor preset: disabled)
   Active: active (running) since Sun 2026-04-26 00:44:31 CET; 5min ago
  Process: 1607316 ExecStart=/usr/sbin/varnishd -a ${VARNISH_LISTEN_ADDRESS}:${VARNISH_LISTEN_PORT} -f ${VARNISH_VCL_CONF} -T ${VARNISH_ADMIN_LISTEN_ADDRESS}:${VARNISH_ADMIN_LISTEN_PORT} -s ${VARNISH_STORAGE} -p thread_pool_min=${VARNISH_MIN_THREADS} -p thread_pool_max=${VARNISH_MAX_THREADS} -p thread_pool_timeout=${VARNISH_THREAD_TIMEOUT} -p workspace_backend=256k -p workspace_client=256k -p http_resp_hdr_len=65536 -p http_resp_size=98304 -p feature=+esi_ignore_https -p vcc_allow_inline_c=on (code=exited, status=0/SUCCESS)
 Main PID: 1607344 (varnishd)
    Tasks: 117 (limit: 203647)
   Memory: 15.5M
   CGroup: /system.slice/varnish.service
           ├─1607344 /usr/sbin/varnishd -a 0.0.0.0:6081 -f /etc/varnish/default.vcl -T 127.0.0.1:6082 -s malloc,4G -p thread_pool_min=50 -p thread_pool_max=1000 -p thread_pool_timeout=120 -p workspace_backend=256k -p workspace_client=256k -p http_resp_hdr_len=65536 -p http_resp_size=98304 -p feature=+esi_ignore_https -p vcc_allow_inline_c=on
           └─1607867 /usr/sbin/varnishd -a 0.0.0.0:6081 -f /etc/varnish/default.vcl -T 127.0.0.1:6082 -s malloc,4G -p thread_pool_min=50 -p thread_pool_max=1000 -p thread_pool_timeout=120 -p workspace_backend=256k -p workspace_client=256k -p http_resp_hdr_len=65536 -p http_resp_size=98304 -p feature=+esi_ignore_https -p vcc_allow_inline_c=on

Apr 26 00:44:31 ded701.inmotionhosting.com varnishd[1607344]: Warnings:
Apr 26 00:44:31 ded701.inmotionhosting.com varnishd[1607344]: VCL compiled.
Apr 26 00:44:31 ded701.inmotionhosting.com varnishd[1607344]: Debug: Version: varnish-6.0.13 revision a395739fa63cddec305142eabefec0a4fd5339e7
Apr 26 00:44:31 ded701.inmotionhosting.com varnishd[1607344]: Debug: Platform: Linux,4.18.0-553.94.1.el8_10.x86_64,x86_64,-junix,-smalloc,-sdefault,-hcritbit

● elasticsearch.service - Elasticsearch
   Loaded: loaded (/etc/systemd/system/elasticsearch.service; disabled; vendor preset: disabled)
  Drop-In: /etc/systemd/system/elasticsearch.service.d
           └─override.conf
   Active: active (running) since Sun 2026-04-26 00:44:28 CET; 5min ago
 Main PID: 1605243 (java)
    Tasks: 90 (limit: 203647)
   Memory: 8.5G
   CGroup: /system.slice/elasticsearch.service
           ├─1605243 /usr/share/elasticsearch/jdk/bin/java -Xshare:auto -Des.networkaddress.cache.ttl=60 -Des.networkaddress.cache.negative.ttl=10 -XX:+AlwaysPreTouch -Xss1m -Djava.awt.headless=true -Dfile.encoding=UTF-8 -Djna.nosys=true -XX:-OmitStackTraceInFastThrow -XX:+ShowCodeDetailsInExceptionMessages -Dio.netty.noUnsafe=true -Dio.netty.noKeySetOptimization=true -Dio.netty.recycler.maxCapacityPerThread=0 -Dio.netty.allocator.numDirectArenas=0 -Dlog4j.shutdownHookEnabled=false -Dlog4j2.disable.jmx=true -Dlog4j2.formatMsgNoLookups=true -Djava.locale.providers=SPI,COMPAT --add-opens=java.base/java.io=ALL-UNNAMED -Djava.security.manager=allow -XX:+UseG1GC -Djava.io.tmpdir=/var/lib/elasticsearch/tmp -XX:+HeapDumpOnOutOfMemoryError -XX:+ExitOnOutOfMemoryError -XX:HeapDumpPath=/var/lib/elasticsearch -XX:ErrorFile=/var/log/elasticsearch/hs_err_pid%p.log -Xlog:gc*,gc+age=trace,safepoint:file=/var/log/elasticsearch/gc.log:utctime,pid,tags:filecount=32,filesize=64m -XX:+UnlockDiagnosticVMOptions -XX:G1NumCollectionsKeepPinned=10000000 -Xms8g -Xmx8g -Djava.io.tmpdir=/var/lib/elasticsearch/tmp -XX:MaxDirectMemorySize=4294967296 -XX:InitiatingHeapOccupancyPercent=30 -XX:G1ReservePercent=25 -Des.path.home=/usr/share/elasticsearch -Des.path.conf=/etc/elasticsearch -Des.distribution.flavor=default -Des.distribution.type=rpm -Des.bundled_jdk=true -cp /usr/share/elasticsearch/lib/* org.elasticsearch.bootstrap.Elasticsearch
           └─1608830 /usr/share/elasticsearch/modules/x-pack-ml/platform/linux-x86_64/bin/controller

Apr 26 00:45:10 ded701.inmotionhosting.com elasticsearch[1605243]:         at java.util.concurrent.ThreadPoolExecutor.runWorker(ThreadPoolExecutor.java:1144) [?:?]
Apr 26 00:45:10 ded701.inmotionhosting.com elasticsearch[1605243]:         at java.util.concurrent.ThreadPoolExecutor$Worker.run(ThreadPoolExecutor.java:642) [?:?]
Apr 26 00:45:10 ded701.inmotionhosting.com elasticsearch[1605243]:         at java.lang.Thread.run(Thread.java:1570) [?:?]
```

