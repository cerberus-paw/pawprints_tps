"""Compone las tapas de los libros 7 a 30 con el diseño de las seis originales.

Mismo formato (900 × 1200), el título arriba en EB Garamond, una línea, el autor en versalitas y
«PAWPrints» al pie. El fondo es una ilustración según el género (paisaje para la ficción, arcos
para la historia, un planeta para la ciencia, formas geométricas para el arte), con una semilla
fija por libro: volver a correrlo da las mismas tapas.

Uso, desde la raíz del repositorio (necesita Pillow y la fuente, que tiene licencia OFL):

    curl -L -o /tmp/EBGaramond.ttf "https://github.com/google/fonts/raw/main/ofl/ebgaramond/EBGaramond%5Bwght%5D.ttf"
    python3 diseño/tapas/componer-tapas.py pawprints/backend/db/libros.sql /tmp/EBGaramond.ttf pawprints/frontend/img
"""
import re, sys, math, random
from PIL import Image, ImageDraw, ImageFont, ImageFilter
SQL, FUENTE, SALIDA = sys.argv[1:4]
W, H = 900, 1200
libros = re.findall(r"\((\d+), '((?:[^']|'')*)', '((?:[^']|'')*)', '([^']*)'", open(SQL, encoding='utf8').read())

def mezclar(a, b, t): return tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3))
def degrade(arriba, abajo):
    im = Image.new('RGB', (W, H)); d = ImageDraw.Draw(im)
    for y in range(H): d.line([(0, y), (W, y)], fill=mezclar(arriba, abajo, y / H))
    return im
def hsv(h, s, v):
    import colorsys; return tuple(int(c * 255) for c in colorsys.hsv_to_rgb(h % 1, s, v))

def ficcion(r):
    h = r.random()
    im = degrade(hsv(h, .55, .22), hsv(h + .08, .45, .55)); d = ImageDraw.Draw(im, 'RGBA')
    # Luna o sol bajo, y capas de colinas cada vez más oscuras.
    cx, cy, rad = r.randint(180, 720), r.randint(740, 840), r.randint(60, 105)
    d.ellipse([cx - rad, cy - rad, cx + rad, cy + rad], fill=hsv(h + .12, .25, .95) + (190,))
    for capa in range(4):
        base = 800 + capa * 110; amp = r.randint(30, 90); fase = r.random() * 6; frec = r.uniform(1.2, 2.8)
        puntos = [(x, base - amp * math.sin(x / W * frec * math.pi + fase)) for x in range(0, W + 10, 10)]
        d.polygon(puntos + [(W, H), (0, H)], fill=hsv(h + .02 * capa, .5, .32 - capa * .07) + (255,))
    return im

def historia(r):
    h = r.uniform(.05, .11)
    im = degrade(hsv(h, .5, .45), hsv(h - .02, .6, .16)); d = ImageDraw.Draw(im, 'RGBA')
    # Arcos y columnas, como una galería vista de frente.
    n = r.choice([3, 4, 5]); ancho = W / n; alto = r.randint(300, 400); piso = 1010
    for i in range(n):
        x0 = i * ancho + ancho * .18; x1 = (i + 1) * ancho - ancho * .18; rad = (x1 - x0) / 2
        d.rectangle([x0, piso - alto + rad, x1, piso], fill=hsv(h, .55, .1) + (150,))
        d.pieslice([x0, piso - alto, x1, piso - alto + 2 * rad], 180, 360, fill=hsv(h, .55, .1) + (150,))
    d.rectangle([0, piso, W, H], fill=hsv(h, .6, .1) + (230,))
    return im

def ciencia(r):
    h = r.uniform(.55, .8)
    im = degrade(hsv(h, .7, .12), hsv(h + .05, .6, .3)); d = ImageDraw.Draw(im, 'RGBA')
    for _ in range(260):
        x, y, s = r.randrange(W), r.randrange(H), r.choice([1, 1, 1, 2, 2, 3])
        d.ellipse([x, y, x + s, y + s], fill=(255, 255, 255, r.randint(90, 230)))
    cx, cy = r.randint(250, 650), r.randint(800, 900)
    for k in range(1, 5):
        rx, ry = 110 + k * 70, 34 + k * 22
        d.ellipse([cx - rx, cy - ry, cx + rx, cy + ry], outline=hsv(h + .1, .3, .9) + (70,), width=2)
    rad = r.randint(60, 95)
    d.ellipse([cx - rad, cy - rad, cx + rad, cy + rad], fill=hsv(h + .45, .55, .75) + (255,))
    d.chord([cx - rad, cy - rad, cx + rad, cy + rad], 300, 120, fill=(0, 0, 0, 90))
    return im

def arte(r):
    h = r.random()
    im = degrade(hsv(h, .35, .4), hsv(h + .1, .4, .22)); d = ImageDraw.Draw(im, 'RGBA')
    paso = r.choice([60, 75, 90])
    for x in range(0, W, paso): d.line([(x, 0), (x, H)], fill=(255, 255, 255, 18))
    for y in range(0, H, paso): d.line([(0, y), (W, y)], fill=(255, 255, 255, 18))
    for _ in range(5):
        x, y, s = r.randrange(60, W - 200), r.randrange(660, 980), r.randint(90, 200)
        color = hsv(h + r.choice([.1, .5, .6]), .55, .8) + (r.randint(120, 200),)
        forma = r.choice(['circulo', 'cuadrado', 'triangulo'])
        if forma == 'circulo': d.ellipse([x, y, x + s, y + s], fill=color)
        elif forma == 'cuadrado': d.rectangle([x, y, x + s, y + s], fill=color)
        else: d.polygon([(x, y + s), (x + s / 2, y), (x + s, y + s)], fill=color)
    return im

FONDOS = {'Ficción': ficcion, 'Historia': historia, 'Ciencia': ciencia, 'Arte & Diseño': arte}
titulo_f = ImageFont.truetype(FUENTE, 62); autor_f = ImageFont.truetype(FUENTE, 32); marca_f = ImageFont.truetype(FUENTE, 26)

def renglones(texto, fuente, ancho):
    palabras, lineas = texto.split(), ['']
    for p in palabras:
        prueba = (lineas[-1] + ' ' + p).strip()
        if fuente.getlength(prueba) <= ancho: lineas[-1] = prueba
        else: lineas.append(p)
    return lineas

def centrado(d, y, texto, fuente, color, espacio=0):
    if espacio:
        ancho = sum(fuente.getlength(c) for c in texto) + espacio * (len(texto) - 1); x = (W - ancho) / 2
        for c in texto: d.text((x, y), c, font=fuente, fill=color); x += fuente.getlength(c) + espacio
    else:
        d.text(((W - fuente.getlength(texto)) / 2, y), texto, font=fuente, fill=color)

hechas = []
for id_, titulo, autor, genero in libros:
    id_ = int(id_)
    if id_ <= 6: continue
    titulo, autor = titulo.replace("''", "'"), autor.replace("''", "'")
    r = random.Random(id_ * 7919)
    im = FONDOS[genero](r).filter(ImageFilter.GaussianBlur(1.2))
    # Un velo oscuro arriba, para que el título se lea sobre cualquier fondo.
    velo = Image.new('L', (W, H)); dv = ImageDraw.Draw(velo)
    for y in range(H): dv.line([(0, y), (W, y)], fill=int(120 * max(0, 1 - y / 520)))
    im = Image.composite(Image.new('RGB', (W, H), (0, 0, 0)), im, velo)
    # Grano fino, como el de las fotos de las originales.
    ruido = Image.effect_noise((W, H), 18).convert('RGB')
    im = Image.blend(im, ruido, .05)
    d = ImageDraw.Draw(im)
    lineas = renglones(titulo, titulo_f, 760)
    y = 165 if len(lineas) == 1 else 130
    for l in lineas: centrado(d, y, l, titulo_f, (245, 240, 232)); y += 74
    d.line([(405, 488), (495, 488)], fill=(235, 228, 218), width=2)
    centrado(d, 520, autor.upper(), autor_f, (240, 234, 226), espacio=2)
    centrado(d, 1110, 'PAWPrints', marca_f, (205, 196, 186))
    ruta = f'{SALIDA}/libro-{id_}.jpg'
    im.save(ruta, quality=84, optimize=True, progressive=True); hechas.append((id_, titulo, genero))
print(len(hechas), 'tapas')
