import { transform } from 'lightningcss';
import postcss from 'postcss';

const OKLCH = /oklch\([^()]*\)/g;

const toRgb = (color) => {
    const { code } = transform({
        filename: 'color.css',
        code: Buffer.from(`a{color:${color}}`),
        minify: true,
        targets: { chrome: 61 << 16 },
    });

    return code.toString().match(/^a\{color:([^;}]+)/)[1];
};

export const addOklchFallbacks = (css) => {
    const root = postcss.parse(css);

    root.walkRules((rule) => {
        if (rule.parent.type === 'atrule' && rule.parent.name === 'supports' && rule.parent.params.includes('oklch')) {
            return;
        }

        const fallbacks = rule.nodes
            .filter((node) => node.type === 'decl' && node.prop.startsWith('--') && node.value.includes('oklch('))
            .map((decl) => decl.clone({ value: decl.value.replace(OKLCH, toRgb) }));

        if (fallbacks.length === 0) {
            return;
        }

        rule.after(
            postcss
                .atRule({ name: 'supports', params: 'not (color: oklch(0 0 0))' })
                .append(rule.clone().removeAll().append(fallbacks)),
        );
    });

    return root.toString();
};

export default function oldWebviewColors() {
    return {
        name: 'old-webview-colors',
        apply: 'build',
        transform(code, id) {
            if (!/\.css(\?|$)/.test(id)) {
                return null;
            }

            return { code: addOklchFallbacks(code), map: null };
        },
    };
}
