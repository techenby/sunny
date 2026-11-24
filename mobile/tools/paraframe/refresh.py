#!/usr/bin/env python3
import argparse
import datetime
import fcntl
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

HERE = Path(__file__).resolve().parent
HOME = Path.home() / 'Library' / 'Application Support' / 'SunnyParaframe'
PARAFRAME = 'http://127.0.0.1:35890'
SUFFIXES = (', as built', ', from code', ', from branch code')

STYLE_PATTERNS = [
    r'^mobile/config/native-ui\.php$',
    r'^mobile/app/Ui/',
    r'^mobile/app/Icons/',
    r'^mobile/app/Layouts/',
    r'^mobile/resources/css/',
    r'^mobile/lang/',
]
PLATFORM_PATTERNS = [
    r'^mobile/composer\.lock$',
    r'^mobile/nativephp\.lock$',
    r'^mobile/patches/',
]
NEW_STATE_PATTERNS = {
    'route': r'Route::native\(',
    'menu': r'Menu::(item|delete)\(',
    'alert': r'Dialog::alert\(',
    'sheet': r'<native:bottom-sheet',
    'empty state': r'<x-ui\.empty-state',
    'inline alert': r'<x-ui\.(alert|offline|not-found)',
}


def log(message):
    stamp = datetime.datetime.now().strftime('%H:%M:%S')
    line = f'[{stamp}] {message}'
    print(line, flush=True)
    if RUN_DIR:
        with open(RUN_DIR / 'run.log', 'a') as handle:
            handle.write(line + '\n')


RUN_DIR = None
LOADED = hashlib.sha256(Path(__file__).read_bytes()).hexdigest()


def sh(args, cwd=None, check=True, capture=True, timeout=None, env=None):
    for attempt in range(4):
        result = subprocess.run(args, cwd=cwd, capture_output=capture, text=True, timeout=timeout, env=env)
        flaky = args[0] == 'axe' and 'remote automation session' in (result.stderr or '') + (result.stdout or '')
        if not flaky or attempt == 3:
            break
        time.sleep(5)
    if check and result.returncode != 0:
        raise RuntimeError(f'{" ".join(map(str, args))} failed: {(result.stderr or result.stdout or "").strip()[-800:]}')
    return result.stdout if capture else ''


def load_config():
    path = HOME / 'config.json'
    if not path.exists():
        sys.exit(f'Missing {path}. See {HERE / "README.md"}.')
    config = json.loads(path.read_text())
    config['worktree'] = Path(os.path.expanduser(config['worktree']))
    return config


def load_state():
    path = HOME / 'state.json'
    return json.loads(path.read_text()) if path.exists() else {}


def save_state(state):
    (HOME / 'state.json').write_text(json.dumps(state, indent=2))


def notify(message):
    subprocess.run(['osascript', '-e', f'display notification {json.dumps(message)} with title "Paraframe refresh"'])


class Server:
    def __init__(self, config):
        settings = config['server']
        self.path = Path(os.path.expanduser(settings['path']))
        self.port = settings['port']
        self.url = f'http://127.0.0.1:{self.port}'
        self.env = {**os.environ, 'DB_DATABASE': settings['database'], 'APP_URL': self.url, 'MAIL_MAILER': 'log',
                    'QUEUE_CONNECTION': 'sync'}
        self.process = None

    def write_env(self):
        env_file = self.path / '.env'
        text = env_file.read_text()
        for key in ('DB_DATABASE', 'APP_URL', 'MAIL_MAILER', 'QUEUE_CONNECTION'):
            line = f'{key}={self.env[key]}'
            text, count = re.subn(rf'^{key}=.*$', line, text, flags=re.M)
            if not count:
                text += f'\n{line}\n'
        env_file.write_text(text)
        sh(['php', 'artisan', 'config:clear'], cwd=self.path, env=self.env)

    def reset(self):
        self.write_env()
        sh(['php', 'artisan', 'migrate:fresh', '--seed', '--force'], cwd=self.path, env=self.env, timeout=600)

    def start(self):
        self.process = subprocess.Popen(['php', 'artisan', 'serve', '--host', '127.0.0.1', '--port', str(self.port)],
                                        cwd=self.path, env=self.env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(30):
            try:
                urllib.request.urlopen(f'{self.url}/up', timeout=2)
                return
            except OSError:
                time.sleep(1)
        raise RuntimeError(f'The local server on port {self.port} did not start')

    def check_login(self, email, password):
        body = json.dumps({'email': email, 'password': password, 'device_name': 'paraframe-preflight'}).encode()
        request = urllib.request.Request(f'{self.url}/api/sanctum/token', data=body, method='POST',
                                         headers={'Content-Type': 'application/json', 'Accept': 'application/json'})
        try:
            urllib.request.urlopen(request, timeout=15)
        except urllib.error.HTTPError as error:
            raise RuntimeError(f'The seeded account cannot sign in to the local server (HTTP {error.code}): {error.read()[:300]!r}')

    def lookup(self, lookups):
        code = 'echo json_encode(collect(json_decode(base64_decode("' + __import__('base64').b64encode(json.dumps(lookups).encode()).decode() + '"), true))->map(fn ($l) => DB::table($l[0])->where("name", $l[1])->value("id")));'
        values = json.loads(sh(['php', 'artisan', 'tinker', '--execute', code], cwd=self.path, env=self.env).strip().splitlines()[-1])
        missing = [k for k, v in values.items() if v is None]
        if missing:
            raise RuntimeError(f'The seed has no record for {", ".join(missing)}; check `lookups` in config.json')
        return {k: str(v) for k, v in values.items()}

    def stop(self):
        if self.process:
            self.process.terminate()
            self.process.wait(timeout=20)


class Simulator:
    def __init__(self, config):
        self.udid = config['simulator']
        self.bundle = config['bundle_id']
        self.root_ca = config.get('root_ca')

    def boot(self):
        state = sh(['xcrun', 'simctl', 'list', 'devices'])
        if f'{self.udid}) (Booted)' not in state:
            sh(['xcrun', 'simctl', 'boot', self.udid], check=False)
            sh(['xcrun', 'simctl', 'bootstatus', self.udid, '-b'], timeout=300)
        if self.root_ca:
            sh(['xcrun', 'simctl', 'keychain', self.udid, 'add-root-cert', os.path.expanduser(self.root_ca)], check=False)
        sh(['xcrun', 'simctl', 'status_bar', self.udid, 'override', '--time', '9:41', '--batteryState', 'charged',
            '--batteryLevel', '100', '--cellularBars', '4', '--wifiBars', '3'])

    def database(self):
        data = sh(['xcrun', 'simctl', 'get_app_container', self.udid, self.bundle, 'data']).strip()
        return Path(data) / 'Library' / 'Application Support' / 'database' / 'database.sqlite'

    def signed_in(self):
        try:
            return sh(['sqlite3', str(self.database()), 'select count(*) from sunny_accounts']).strip() not in ('', '0')
        except RuntimeError:
            return False

    def field(self, label):
        hits = [n for n in self.nodes() if n.get('type') in ('TextField', 'SecureTextField') and n.get('AXLabel') == label]
        return hits[-1] if hits else None

    def fill_field(self, label, text, secret=False):
        expected = '\u2022' * len(text) if secret else text
        for _ in range(3):
            node = self.field(label)
            if not node:
                raise RuntimeError(f'Could not find the "{label}" field')
            self.press(node)
            time.sleep(1.5)
            sh(['axe', 'key-sequence', '--keycodes', ','.join(['42'] * 40), '--udid', self.udid], check=False)
            sh(['axe', 'type', text, '--udid', self.udid])
            time.sleep(1)
            if (self.field(label) or {}).get('AXValue') == expected:
                return
        raise RuntimeError(f'Typing into the "{label}" field did not stick')

    def sign_in(self, email, password):
        self.open('/login')
        self.dismiss_prompts()
        self.fill_field('Email address', email)
        self.fill_field('Password', password, secret=True)
        sh(['axe', 'touch', '-x', '201', '-y', '130', '--down', '--up', '--delay', '0.1', '--udid', self.udid], check=False)
        time.sleep(2)
        buttons = [n for n in self.nodes() if n.get('type') == 'Button' and n.get('AXLabel') == 'Log in']
        if not buttons:
            raise RuntimeError('Could not find the Log in button')
        self.press(max(buttons, key=lambda n: n['frame']['y']))
        for _ in range(20):
            time.sleep(3)
            if self.signed_in():
                time.sleep(15)
                return
        raise RuntimeError('Signing in on the simulator did not work')

    def dismiss_prompts(self):
        for _ in range(3):
            if not self.has_label_prefix('Help improve the app?'):
                return
            self.tap_exact_button('Not now')
            time.sleep(1.5)

    def restart(self):
        sh(['xcrun', 'simctl', 'terminate', self.udid, self.bundle], check=False)
        time.sleep(1)
        self.launch()
        time.sleep(4)
        self.dismiss_prompts()

    def launch(self):
        sh(['xcrun', 'simctl', 'launch', self.udid, self.bundle], check=False)
        time.sleep(8)

    def open(self, route, wait=8):
        sh(['xcrun', 'simctl', 'openurl', self.udid, 'sunnyhome:/' + route])
        time.sleep(wait)
        if self.has_label_prefix('Open in'):
            raise RuntimeError('iOS asked to confirm the deep link; launch the app before opening links')

    def nodes(self):
        tree = json.loads(sh(['axe', 'describe-ui', '--udid', self.udid]))
        found = []

        def walk(node):
            if isinstance(node, list):
                for child in node:
                    walk(child)
                return
            found.append(node)
            for child in node.get('children') or []:
                walk(child)

        walk(tree)
        return [n for n in found if n.get('frame', {}).get('width', 0) > 0 and n.get('frame', {}).get('height', 0) > 0]

    def has_label_prefix(self, prefix):
        return any((n.get('AXLabel') or '').startswith(prefix) for n in self.nodes())

    def find(self, label, buttons_only=False):
        for _ in range(5):
            nodes = self.nodes()
            buttons = [n for n in nodes if n.get('type') in ('Button', 'PopUpButton')]
            for pool, match in [
                (buttons, lambda l: l == label),
                (buttons, lambda l: l.startswith(label)),
                (buttons, lambda l: label in l),
                ([] if buttons_only else nodes, lambda l: l == label),
            ]:
                hits = [n for n in pool if match(n.get('AXLabel') or '')]
                if hits:
                    return hits[-1]
            time.sleep(1.5)
        return None

    def press(self, node):
        frame = node['frame']
        x, y = round(frame['x'] + frame['width'] / 2), round(frame['y'] + frame['height'] / 2)
        sh(['axe', 'touch', '-x', str(x), '-y', str(y), '--down', '--up', '--delay', '0.15', '--udid', self.udid])

    def tap(self, label, wait=2):
        node = self.find(label)
        if not node:
            raise RuntimeError(f'Could not find "{label}" on screen')
        self.press(node)
        time.sleep(wait)

    def tap_exact_button(self, label):
        hits = [n for n in self.nodes() if n.get('type') == 'Button' and n.get('AXLabel') == label]
        if not hits:
            return False
        self.press(hits[-1])
        time.sleep(1.5)
        return True

    def swipe_up(self):
        sh(['axe', 'swipe', '--udid', self.udid, '--start-x', '201', '--start-y', '640', '--end-x', '201',
            '--end-y', '240', '--duration', '0.6'], check=False)
        time.sleep(1.2)

    def swipe_down(self):
        sh(['axe', 'swipe', '--udid', self.udid, '--start-x', '201', '--start-y', '200', '--end-x', '201',
            '--end-y', '850', '--duration', '0.4'], check=False)
        time.sleep(1.5)

    def screenshot(self, path):
        sh(['axe', 'screenshot', '--udid', self.udid, '--output', str(path)])

    def search(self, word):
        fields = [n for n in self.nodes() if n.get('type') == 'TextField']
        if not fields:
            raise RuntimeError('No search field on screen')
        frame = fields[-1]['frame']
        sh(['axe', 'touch', '-x', str(round(frame['x'] + frame['width'] - 4)), '-y',
            str(round(frame['y'] + frame['height'] / 2)), '--down', '--up', '--delay', '0.15', '--udid', self.udid])
        time.sleep(1)
        sh(['axe', 'key-sequence', '--keycodes', ','.join(['42'] * 30), '--udid', self.udid], check=False)
        if word:
            sh(['axe', 'type', word, '--udid', self.udid])
        time.sleep(1)
        sh(['axe', 'key', '40', '--udid', self.udid], check=False)
        time.sleep(2.5)

    def switch_team(self, name):
        self.open('/preferences')
        self.dismiss_prompts()
        if self.find(f'{name}, selected', buttons_only=True):
            return
        if not self.tap_exact_button(name):
            raise RuntimeError(f'Could not find the team "{name}" in Settings')
        time.sleep(3)
        if not self.find(f'{name}, selected', buttons_only=True):
            raise RuntimeError(f'Switching to the team "{name}" did not stick')


def fill(text, values):
    return re.sub(r'\{(\w+)\}', lambda m: str(values[m.group(1)]), text)


def digest(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def capture(sim, frame, values, out):
    slug = re.sub(r'[^a-z0-9]+', '-', frame['title'].lower()).strip('-')
    if frame.get('steps') or frame.get('dismiss'):
        sim.restart()
    sim.open(fill(frame['route'], values))
    sim.dismiss_prompts()
    for step in frame.get('steps', []):
        if 'tap' in step:
            sim.tap(fill(step['tap'], values))
        elif 'search' in step:
            sim.search(step['search'])
        elif 'scroll_to' in step:
            for _ in range(8):
                node = sim.find(fill(step['scroll_to'], values), buttons_only=True)
                if node and node['frame']['y'] + node['frame']['height'] < 760:
                    break
                sim.swipe_up()
    shots = [out / f'{slug}-0.png']
    sim.screenshot(shots[0])
    if frame.get('full_page'):
        for n in range(1, 10):
            sim.swipe_up()
            path = out / f'{slug}-{n}.png'
            sim.screenshot(path)
            if digest(path) == digest(shots[-1]):
                path.unlink()
                break
            shots.append(path)
    dismiss = frame.get('dismiss')
    if dismiss == 'alert':
        if not (sim.tap_exact_button('Cancel') or sim.tap_exact_button('Keep')):
            raise RuntimeError(f'An alert for "{frame["title"]}" has no Cancel or Keep button; stopping before anything is tapped')
        if any(n.get('type') == 'Button' and n.get('AXLabel') in ('Cancel', 'Keep') for n in sim.nodes()):
            raise RuntimeError(f'The alert for "{frame["title"]}" did not close; stopping')
    elif dismiss == 'sheet':
        if not sim.tap_exact_button('Close'):
            sim.swipe_down()
    elif dismiss == 'menu':
        sh(['axe', 'touch', '-x', '201', '-y', '30', '--down', '--up', '--delay', '0.1', '--udid', sim.udid], check=False)
        time.sleep(1)
    return [str(p) for p in shots]


def capture_all(config, sim, server, to_capture, captured, failures):
    sim.launch()
    time.sleep(10)
    if not sim.signed_in():
        log('Signing in')
        sim.sign_in(config['account']['email'], config['account']['password'])
    sim.dismiss_prompts()
    values = {**server.lookup(config['lookups']), **config['labels']}
    home, empty = config['labels']['home_team'], config['labels']['empty_team']
    ordered = [h for h in to_capture if h.get('team') != 'empty'] + [h for h in to_capture if h.get('team') == 'empty']
    switched = False
    try:
        for entry in ordered:
            if entry.get('team') == 'empty' and not switched:
                sim.switch_team(empty)
                switched = True
            try:
                captured[entry['frame']] = capture(sim, entry, values, RUN_DIR / 'shots')
                log(f'Captured {entry["title"]}')
            except RuntimeError as error:
                failures.append(f'{entry["title"]}: {error}')
                log(f'Could not capture {entry["title"]}: {error}')
                if 'stopping' in str(error):
                    break
                sim.launch()
    finally:
        if switched:
            sim.switch_team(home)



def board_frames(board):
    with urllib.request.urlopen(f'{PARAFRAME}/boards/{board}', timeout=10) as response:
        frames = json.load(response)['frames']
    return {f['reference']: f for f in frames if f['title'].endswith(SUFFIXES)}


def dependencies(mobile, files):
    files = [f if f.startswith('mobile/') else 'mobile/' + f for f in files]
    seen, queue = set(), list(files)
    while queue:
        path = queue.pop()
        if path in seen:
            continue
        seen.add(path)
        full = mobile.parent / path
        if not full.exists():
            continue
        text = full.read_text(errors='ignore')
        if path.endswith('.blade.php'):
            for name in re.findall(r"@include(?:If|When)?\(\s*'([\w.-]+)'", text):
                queue.append('mobile/resources/views/' + name.replace('.', '/') + '.blade.php')
            for name in re.findall(r'<x-([\w.-]+)', text):
                queue.append('mobile/resources/views/components/' + name.replace('.', '/') + '.blade.php')
        elif path.endswith('.php'):
            for name in re.findall(r'^use App\\((?:Concerns|Support|Http)\\[\w\\]+);', text, re.M):
                queue.append('mobile/app/' + name.replace('\\', '/') + '.php')
    return seen


def affected_frames(config, manifest, frames, changed):
    mobile = config['worktree'] / 'mobile'
    style = any(re.search(p, f) for p in STYLE_PATTERNS for f in changed)
    platform = any(re.search(p, f) for p in PLATFORM_PATTERNS for f in changed)
    direct, check = [], []
    for entry in manifest['frames']:
        frame = frames.get(entry['frame'])
        if not frame:
            continue
        files = ((frame.get('origin') or {}).get('screen') or {}).get('files') or []
        if style or dependencies(mobile, files) & set(changed):
            direct.append(entry)
        elif platform and not entry.get('code'):
            check.append(entry)
    return direct, check, style, platform


def new_state_hints(worktree, base, head):
    files = sh(['git', 'diff', '--name-only', base, head, '--', 'mobile/app', 'mobile/resources/views', 'mobile/routes'], cwd=worktree).split()
    hints = []
    for path in files:
        if 'ui-kit' in path or 'UiKit' in path:
            continue
        before = subprocess.run(['git', 'show', f'{base}:{path}'], cwd=worktree, capture_output=True, text=True).stdout
        after = subprocess.run(['git', 'show', f'{head}:{path}'], cwd=worktree, capture_output=True, text=True).stdout
        for kind, pattern in NEW_STATE_PATTERNS.items():
            old_lines = {l.strip() for l in before.splitlines() if re.search(pattern, l)}
            added = [l.strip() for l in after.splitlines() if re.search(pattern, l) and l.strip() not in old_lines]
            if len(re.findall(pattern, after)) > len(re.findall(pattern, before)):
                hints += [f'{kind} in `{path}`: `{l[:120]}`' for l in added]
    return hints


def screenshots_changed(shots, title):
    previous = sorted((HOME / 'shots').glob(slugify(title) + '-*.png'), key=lambda p: int(p.stem.rsplit('-', 1)[1]))
    if len(previous) != len(shots):
        return True
    return any(digest(a) != digest(b) for a, b in zip(previous, shots))


def slugify(title):
    return re.sub(r'[^a-z0-9]+', '-', title.lower()).strip('-')


def run_agents(config, batches, diff_file):
    brief = HERE / 'brief.md'

    def run(batch_number, batch):
        folder = RUN_DIR / f'agent-{batch_number}'
        folder.mkdir()
        (folder / 'frames.json').write_text(json.dumps(batch, indent=2, ensure_ascii=False))
        prompt = (
            f'Read {brief} and follow it. '
            f'Your frames are in {folder / "frames.json"}; the code changes since the board was last updated are in {diff_file}. '
            f'The app code is in {config["worktree"] / "mobile"}. Work in {folder} and write {folder / "REPORT.md"} when done.'
        )
        command = list(config['agent']['command'])
        if Path(command[0]).name == 'claude':
            dirs = [str(RUN_DIR), str(HOME / 'shots'), str(config['worktree'] / 'mobile'), str(HERE)]
            args = command + ['--add-dir', *dirs, '-p', prompt]
        else:
            args = command + ['-C', str(folder), '-o', str(folder / 'last-message.txt'), prompt]
        log(f'Agent {batch_number}: {len(batch)} frames')
        result = subprocess.run(args, cwd=folder, capture_output=True, text=True, timeout=config['agent'].get('timeout', 3600))
        (folder / 'agent.log').write_text((result.stdout or '') + (result.stderr or ''))
        report = folder / 'REPORT.md'
        return report.read_text() if report.exists() else f'Agent {batch_number} wrote no report (exit {result.returncode}); see {folder / "agent.log"}'

    with ThreadPoolExecutor(max_workers=config['agent'].get('parallel', 3)) as pool:
        return list(pool.map(lambda item: run(*item), enumerate(batches, 1)))


def keychain(account):
    result = subprocess.run(['security', 'find-generic-password', '-s', 'sunny-paraframe-trello', '-a', account, '-w'],
                            capture_output=True, text=True)
    return result.stdout.strip() if result.returncode == 0 else None


def trello(method, path, **params):
    params.update(key=keychain('key'), token=keychain('token'))
    data = urllib.parse.urlencode(params).encode() if method == 'POST' else None
    url = f'https://api.trello.com/1{path}' + ('' if data else '?' + urllib.parse.urlencode(params))
    with urllib.request.urlopen(urllib.request.Request(url, data=data, method=method), timeout=20) as response:
        return json.load(response)


def report(config, title, body):
    (RUN_DIR / 'report.md').write_text(f'# {title}\n\n{body}\n')
    if not (keychain('key') and keychain('token')):
        notify(f'{title}. No Trello key in the keychain; report saved to {RUN_DIR / "report.md"}')
        return None
    settings = config['trello']
    lists = trello('GET', f'/boards/{settings["board"]}/lists')
    labels = trello('GET', f'/boards/{settings["board"]}/labels')
    list_id = next(l['id'] for l in lists if l['name'] == settings['list'])
    label_ids = [l['id'] for l in labels if l['name'] in settings['labels']]
    card = trello('POST', '/cards', idList=list_id, name=title, desc=body[:16000], idLabels=','.join(label_ids), pos='top')
    notify(f'{title}: {card["shortUrl"]}')
    return card['shortUrl']


def board_link(config, refs, frames):
    ids = [frames[r]['id'] for r in refs if r in frames]
    return f'https://paraframe.app/boards/{config["board"]}' + (f'?frames={",".join(ids)}' if ids else '')


def command_run(args):
    global RUN_DIR
    config, state = load_config(), load_state()
    worktree = config['worktree']
    manifest = json.loads((HERE / 'manifest.json').read_text())
    if args.manifest:
        manifest = json.loads(Path(args.manifest).read_text())

    sh(['git', 'fetch', '--quiet', 'origin', 'main'], cwd=worktree)
    head = sh(['git', 'rev-parse', 'origin/main'], cwd=worktree).strip()
    base = args.since or state.get('last_sha')
    if not base:
        sys.exit('No baseline yet: run `refresh.py baseline` first.')
    if head == base and not args.since:
        print('origin/main has not moved; nothing to do.')
        return

    RUN_DIR = HOME / 'runs' / datetime.datetime.now().strftime('%Y-%m-%d-%H%M%S')
    (RUN_DIR / 'shots').mkdir(parents=True)
    short = head[:8]
    log(f'Refreshing from {base[:8]} to {short}')

    try:
        urllib.request.urlopen(f'{PARAFRAME}/boards', timeout=5).read()
    except OSError:
        notify('Paraframe is not open. Open it and run the refresh again.')
        log('Paraframe is not open; stopping without changing anything')
        return

    if sh(['git', 'status', '--porcelain', '--untracked-files=no'], cwd=worktree).strip():
        raise RuntimeError(f'{worktree} has local changes; refusing to switch it')

    changed = [f for f in sh(['git', 'diff', '--name-only', base, head], cwd=worktree).split() if f.startswith('mobile/')]
    if not changed:
        log('No mobile changes; moving the baseline forward')
        state['last_sha'] = head
        save_state(state)
        return

    sh(['git', 'checkout', '--quiet', '--detach', head], cwd=worktree)
    if digest(Path(__file__)) != LOADED and not os.environ.get('PARAFRAME_RESTARTED'):
        log('refresh.py changed in this update; restarting with the new version')
        os.execve(sys.executable, [sys.executable, '-I', __file__, *sys.argv[1:]], {**os.environ, 'PARAFRAME_RESTARTED': '1'})
    for app in ('mobile', 'web'):
        if f'{app}/composer.lock' in sh(['git', 'diff', '--name-only', base, head], cwd=worktree).split():
            log(f'{app}/composer.lock changed; installing')
            sh(['composer', 'install', '--no-interaction', '--quiet'], cwd=worktree / app, timeout=900)

    frames = board_frames(config['board'])
    direct, check, style, platform = affected_frames(config, manifest, frames, changed)
    if args.all:
        direct, check = [e for e in manifest['frames'] if e['frame'] in frames], []
    hints = new_state_hints(worktree, base, head)
    log(f'{len(changed)} mobile files changed; {len(direct)} frames draw from them'
        + (' (shared styling changed, so all of them)' if style else '')
        + (f'; {len(check)} more will be captured to compare, since NativePHP or its patches changed' if check else ''))

    diff_file = RUN_DIR / 'changes.diff'
    diff_file.write_text(sh(['git', 'diff', base, head, '--', 'mobile/app', 'mobile/resources', 'mobile/routes', 'mobile/config', 'mobile/lang', 'mobile/patches'], cwd=worktree))

    sim = Simulator(config)
    server = Server(config)
    hits = direct + check
    to_capture = [h for h in hits if not h.get('code')]
    captured, failures = {}, []
    try:
        if to_capture:
            log('Seeding and starting the local server')
            server.reset()
            server.start()
            server.check_login(config['account']['email'], config['account']['password'])
            env_file = worktree / 'mobile' / '.env'
            env_text = re.sub(r'^SUNNY_API_URL=.*$', f'SUNNY_API_URL={server.url}/api', env_file.read_text(), flags=re.M)
            env_file.write_text(env_text)
        if to_capture and not args.no_build:
            sim.boot()
            log('Building and installing on the simulator')
            sh(['xcrun', 'simctl', 'uninstall', sim.udid, sim.bundle], check=False)
            sh(['php', 'artisan', 'native:run', 'ios', sim.udid, '--no-tty', '-n'], cwd=worktree / 'mobile', timeout=1800)
            sim.boot()
        if to_capture:
            capture_all(config, sim, server, to_capture, captured, failures)
    finally:
        server.stop()

    previous = HOME / 'shots'
    previous.mkdir(exist_ok=True)
    direct_refs = {e['frame'] for e in direct}
    work, unchanged = [], []
    for entry in hits:
        frame = frames[entry['frame']]
        shots = captured.get(entry['frame'], [])
        if not entry.get('code') and not shots:
            continue
        if entry['frame'] not in direct_refs and not screenshots_changed(shots, entry['title']):
            unchanged.append(entry['title'])
            continue
        work.append({
            'frame': entry['frame'],
            'title': entry['title'],
            'kind': 'from code' if entry.get('code') else 'as built',
            'route': entry['route'],
            'source_files': ((frame.get('origin') or {}).get('screen') or {}).get('files') or [],
            'screenshots': shots,
            'previous_screenshots': [str(p) for p in sorted(previous.glob(slugify(entry['title']) + '-*.png'))],
        })
    if check:
        log(f'{len(unchanged)} of the {len(check)} compared screens look the same as last time')

    reports = []
    if work and not args.no_agents:
        size = config['agent'].get('batch', 6)
        reports = run_agents(config, [work[i:i + size] for i in range(0, len(work), size)], diff_file)

    if not args.no_agents:
        for entry_shots in captured.values():
            if not entry_shots:
                continue
            stem = re.sub(r'-\d+\.png$', '', Path(entry_shots[0]).name)
            for old in previous.glob(stem + '-*.png'):
                old.unlink()
            for shot in entry_shots:
                shutil.copy(shot, previous / Path(shot).name)

    lines = [f'**main:** `{base[:8]}` → `{short}`', f'**Run folder:** `{RUN_DIR}`', '']
    if not work:
        lines.append('No frames on the board are drawn from the files that changed.')
    else:
        lines += [f'**Frames checked ({len(work)}):** {board_link(config, [w["frame"] for w in work], frames)}', '']
        lines += [r.strip() for r in reports] or [f'- {w["title"]}' for w in work]
    if failures:
        lines += ['', '**Could not capture:**'] + [f'- {f}' for f in failures]
    if hints:
        lines += ['', '**New in the code, maybe needing a frame:**'] + [f'- {h}' for h in hints[:40]]
    body = '\n'.join(lines)
    url = None if args.no_report else report(config, f'Paraframe refresh: main {short}', body)
    log(f'Report: {url or RUN_DIR / "report.md"}')

    if not failures and not (args.since or args.no_agents or args.no_report or args.manifest):
        state['last_sha'] = head
        save_state(state)


def command_baseline(args):
    config, state = load_config(), load_state()
    sha = args.sha or sh(['git', 'rev-parse', 'HEAD'], cwd=config['worktree']).strip()
    state['last_sha'] = sha
    save_state(state)
    print(f'Baseline set to {sha[:8]}')


def main():
    parser = argparse.ArgumentParser(description='Keep the SunnyHome mobile Paraframe board in step with main.')
    commands = parser.add_subparsers(dest='command', required=True)
    run = commands.add_parser('run')
    run.add_argument('--since', help='Compare from this commit instead of the last run (does not move the baseline)')
    run.add_argument('--manifest', help='Use another manifest file (for trying a few frames)')
    run.add_argument('--no-build', action='store_true')
    run.add_argument('--all', action='store_true', help='Treat every frame as affected')
    run.add_argument('--no-agents', action='store_true')
    run.add_argument('--no-report', action='store_true')
    baseline = commands.add_parser('baseline')
    baseline.add_argument('sha', nargs='?')
    args = parser.parse_args()

    HOME.mkdir(parents=True, exist_ok=True)
    if args.command != 'run':
        command_baseline(args)
        return
    with open(HOME / 'lock', 'w') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            sys.exit('Another refresh is running.')
        try:
            command_run(args)
        except Exception as error:
            log(f'Failed: {error}')
            notify(f'Refresh failed: {error}'[:200])
            raise


if __name__ == '__main__':
    main()
