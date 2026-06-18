import pexpect
import sys

host = sys.argv[1]
user = sys.argv[2]
password = sys.argv[3]
local_file = sys.argv[4]
remote_file = sys.argv[5]

child = pexpect.spawn(f'scp -o StrictHostKeyChecking=no {local_file} {user}@{host}:{remote_file}')
child.expect('password:')
child.sendline(password)
child.expect(pexpect.EOF)
