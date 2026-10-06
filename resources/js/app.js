import './bootstrap';
import Alpine from 'alpinejs';

/**
 * Assistente de votação (UX). Nenhuma regra de negócio vive aqui:
 * o backend revalida tudo (status, janela de tempo, alternativas, duplicidade).
 */
Alpine.data('wizard', ({ perguntas, permiteVoltar, minutos }) => ({
    perguntas,
    permiteVoltar,
    minutos,
    etapa: 'intro', // 'intro' | índice | 'revisao'
    respostas: {},
    erro: '',
    enviando: false,

    get pares() {
        return Object.entries(this.respostas).flatMap(([pid, ids]) => ids.map(id => ({ pid, id })));
    },
    get total() { return this.perguntas.length; },
    get indice() { return typeof this.etapa === 'number' ? this.etapa : null; },
    get atual() { return this.indice === null ? null : this.perguntas[this.indice]; },
    get concluido() {
        if (this.etapa === 'intro') return 0;
        if (this.etapa === 'revisao') return 100;
        return Math.round((this.etapa / this.total) * 100);
    },

    iniciar() { this.etapa = 0; this.foco(); },

    selecionado(p, id) { return (this.respostas[p.id] || []).includes(id); },

    selecionar(p, id) {
        this.erro = '';
        if (p.multipla) {
            const lista = this.respostas[p.id] ? [...this.respostas[p.id]] : [];
            const i = lista.indexOf(id);
            i >= 0 ? lista.splice(i, 1) : lista.push(id);
            this.respostas[p.id] = lista;
        } else {
            this.respostas[p.id] = [id];
        }
    },

    proxima() {
        const p = this.atual;
        if (p && p.obrigatoria && !(this.respostas[p.id] || []).length) {
            this.erro = 'Selecione uma opção para continuar.';
            return;
        }
        this.erro = '';
        this.etapa = this.etapa + 1 >= this.total ? 'revisao' : this.etapa + 1;
        this.foco();
    },

    voltar() {
        this.erro = '';
        if (this.etapa === 'revisao') this.etapa = this.total - 1;
        else if (this.etapa > 0) this.etapa -= 1;
        this.foco();
    },

    editar(i) { if (this.permiteVoltar) { this.etapa = i; this.foco(); } },

    textos(p) {
        const ids = this.respostas[p.id] || [];
        const t = p.alternativas.filter(a => ids.includes(a.id)).map(a => a.texto);
        return t.length ? t.join(', ') : 'Não respondida';
    },

    foco() { this.$nextTick(() => this.$refs.titulo?.focus()); window.scrollTo({ top: 0 }); },

    enviar() {
        if (this.enviando) return; // evita duplo clique; o banco também impede duplicidade
        this.enviando = true;
        this.$refs.form.submit();
    },
}));

/** Formulário de criação/edição de votação. */
Alpine.data('votacaoForm', ({ perguntas, tipos }) => ({
    tipos,
    perguntas: (perguntas || []).map(p => ({ ...p, alternativas: [...(p.alternativas || [])], obrigatoria: !!p.obrigatoria })),

    init() { if (this.perguntas.length === 0) this.novaPergunta(); },

    novaPergunta() {
        this.perguntas.push({ tipo: 'escolha_unica', titulo: '', descricao: '', obrigatoria: true, alternativas: ['', ''] });
    },
    remover(i) { this.perguntas.splice(i, 1); },
    mover(i, d) {
        const j = i + d;
        if (j < 0 || j >= this.perguntas.length) return;
        [this.perguntas[i], this.perguntas[j]] = [this.perguntas[j], this.perguntas[i]];
    },
    fixa(p) { return this.tipos[p.tipo]?.fixa; },
    addAlt(p) { p.alternativas.push(''); },
    remAlt(p, j) { p.alternativas.splice(j, 1); },
}));

/** Resultados com atualização automática (polling leve sobre JSON em cache). */
Alpine.data('resultados', ({ url, inicial, ao_vivo }) => ({
    r: inicial,
    atualizadoEm: new Date(),
    ao_vivo,
    init() {
        if (!this.ao_vivo) return;
        setInterval(async () => {
            if (document.hidden) return;
            try {
                const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (res.ok) { this.r = await res.json(); this.atualizadoEm = new Date(); }
            } catch (e) { /* mantém último valor */ }
        }, 5000);
    },
    fmt(n) { return new Intl.NumberFormat('pt-BR').format(n); },
}));

window.Alpine = Alpine;
Alpine.start();
