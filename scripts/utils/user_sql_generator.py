# default user configs
oj_password_suffix = 'PLEASE CHANGE ME'
is_enabled = 1
expiration = '' # or '2019-08-31 23:59:00'

import hashlib
def md5(text):
    lib = hashlib.md5()
    text = text.encode(encoding = 'utf-8')
    lib.update(text)
    return lib.hexdigest()

with open('userinfo.txt', 'r', encoding = 'utf-8') as f:
    data = [user.split() for user in f.read().splitlines()]

output = []
for user in data:
    sql = 'insert into User (isEnabled, priviledge, expiration, name, email, password'
    if len(user) > 3:
        sql = sql + ', school'
    sql = sql + ") values (%d, 'user', %s, '%s', '%s', '%s'" % (is_enabled, ("'"+expiration+"'" if len(expiration) else 'null'), user[0], user[1], md5(md5(user[2]) + oj_password_suffix))
    if len(user) > 3:
        sql = sql + ", '%s'" % user[3]
    sql = sql + ');'
    output.append(sql)

with open('userinfo.sql', 'w', encoding = 'utf-8') as f:
    for user in output:
        f.write("%s\n" % user)
