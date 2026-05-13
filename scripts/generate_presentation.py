"""
iScan-CRDMS Executive Presentation Generator (Enhanced Design)

Builds a 22-slide PowerPoint deck for an executive (Mayor / Civil Registrar)
audience with richer visual design and expanded narrative copy.

Run:
    python scripts/generate_presentation.py

Output:
    documents/iScan_Executive_Presentation.pptx
"""

import os
from pptx import Presentation
from pptx.util import Inches, Pt, Emu
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import PP_ALIGN, MSO_ANCHOR
from pptx.oxml.ns import qn
from lxml import etree


# ----------------------------------------------------------------------------
# Theme
# ----------------------------------------------------------------------------

NAVY = RGBColor(0x0B, 0x25, 0x45)
NAVY_MID = RGBColor(0x14, 0x33, 0x5C)
NAVY_LIGHT = RGBColor(0x1B, 0x3A, 0x6B)
NAVY_DEEP = RGBColor(0x06, 0x18, 0x30)
TEAL = RGBColor(0x0E, 0x76, 0x90)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
DARK_TEXT = RGBColor(0x1F, 0x29, 0x37)
BODY_TEXT = RGBColor(0x37, 0x41, 0x51)
GRAY_MID = RGBColor(0x6B, 0x72, 0x80)
GRAY_LIGHT = RGBColor(0xE5, 0xE7, 0xEB)
GRAY_BG = RGBColor(0xF9, 0xFA, 0xFB)
AMBER = RGBColor(0xF5, 0x9E, 0x0B)
AMBER_FILL = RGBColor(0xFE, 0xF3, 0xC7)
AMBER_BORDER = RGBColor(0xB4, 0x53, 0x09)
AMBER_TEXT = RGBColor(0x7C, 0x2D, 0x12)
PLACEHOLDER_FILL = RGBColor(0xF3, 0xF4, 0xF6)
PLACEHOLDER_BORDER = RGBColor(0x9C, 0xA3, 0xAF)

SLIDE_W = Inches(13.333)
SLIDE_H = Inches(7.5)

TITLE_BAR_H = Inches(1.05)
FOOTER_H = Inches(0.35)
ACCENT_BAR_W = Inches(0.18)

ICON_SIZE = Inches(1.0)
ICON_LEFT = Inches(0.55)
ICON_TOP = Inches(1.32)

NARRATIVE_LEFT = Inches(1.85)
NARRATIVE_TOP = Inches(1.25)
NARRATIVE_W_FULL = Inches(10.95)
NARRATIVE_W_WITH_SHOT = Inches(6.85)
NARRATIVE_H = Inches(1.3)

BULLETS_TOP = Inches(2.65)
BULLETS_H = Inches(3.05)

SHOT_LEFT = Inches(8.95)
SHOT_TOP = Inches(1.35)
SHOT_W = Inches(3.95)
SHOT_H = Inches(4.35)

CALLOUT_LEFT = Inches(0.55)
CALLOUT_W = Inches(12.25)
CALLOUT_H = Inches(0.95)
CALLOUT_TOP = Inches(5.95)


# ----------------------------------------------------------------------------
# XML / fill helpers
# ----------------------------------------------------------------------------

def _hex(color):
    return f"{color[0]:02X}{color[1]:02X}{color[2]:02X}"


def set_solid_fill(shape, color):
    shape.fill.solid()
    shape.fill.fore_color.rgb = color


def set_gradient_fill(shape, c1, c2, angle=0):
    """Apply a two-stop linear gradient by manipulating the spPr XML."""
    sp = shape.fill._xPr  # SpPr
    # Remove existing fill children
    for tag in ("a:solidFill", "a:gradFill", "a:noFill",
                "a:blipFill", "a:pattFill"):
        for el in sp.findall(qn(tag)):
            sp.remove(el)
    grad = etree.SubElement(sp, qn("a:gradFill"))
    grad.set("flip", "none")
    grad.set("rotWithShape", "1")
    gs_lst = etree.SubElement(grad, qn("a:gsLst"))
    gs1 = etree.SubElement(gs_lst, qn("a:gs"))
    gs1.set("pos", "0")
    s1 = etree.SubElement(gs1, qn("a:srgbClr"))
    s1.set("val", f"{c1[0]:02X}{c1[1]:02X}{c1[2]:02X}")
    gs2 = etree.SubElement(gs_lst, qn("a:gs"))
    gs2.set("pos", "100000")
    s2 = etree.SubElement(gs2, qn("a:srgbClr"))
    s2.set("val", f"{c2[0]:02X}{c2[1]:02X}{c2[2]:02X}")
    lin = etree.SubElement(grad, qn("a:lin"))
    lin.set("ang", str(angle * 60000))
    lin.set("scaled", "0")
    tile = etree.SubElement(grad, qn("a:tileRect"))


def set_no_fill(shape):
    shape.fill.background()


def set_line(shape, color, width_pt=1.0):
    shape.line.color.rgb = color
    shape.line.width = Pt(width_pt)


def set_no_line(shape):
    shape.line.fill.background()


def set_dashed_line(shape, color, width_pt=1.5):
    shape.line.color.rgb = color
    shape.line.width = Pt(width_pt)
    ln = shape.line._get_or_add_ln()
    for child in ln.findall(qn("a:prstDash")):
        ln.remove(child)
    dash = etree.SubElement(ln, qn("a:prstDash"))
    dash.set("val", "dash")


def add_text(shape, text, *, size=14, bold=False, italic=False, color=DARK_TEXT,
             align=PP_ALIGN.LEFT, anchor=MSO_ANCHOR.MIDDLE, font="Calibri"):
    tf = shape.text_frame
    tf.word_wrap = True
    tf.vertical_anchor = anchor
    tf.margin_left = Inches(0.05)
    tf.margin_right = Inches(0.05)
    tf.margin_top = Inches(0.03)
    tf.margin_bottom = Inches(0.03)
    p = tf.paragraphs[0]
    p.alignment = align
    run = p.add_run()
    run.text = text
    run.font.name = font
    run.font.size = Pt(size)
    run.font.bold = bold
    run.font.italic = italic
    run.font.color.rgb = color


# ----------------------------------------------------------------------------
# Slide chrome (gradient title bar, accent column, footer)
# ----------------------------------------------------------------------------

def add_slide_background(slide):
    bg = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, SLIDE_W, SLIDE_H)
    set_solid_fill(bg, WHITE)
    set_no_line(bg)
    # subtle off-white wash on the lower band
    wash = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE,
                                   0, Inches(5.85), SLIDE_W, Inches(1.65))
    set_solid_fill(wash, GRAY_BG)
    set_no_line(wash)


def add_title_bar(slide, title, eyebrow=None):
    # Gradient bar
    bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, SLIDE_W, TITLE_BAR_H)
    set_solid_fill(bar, NAVY)  # initialise fill node
    set_gradient_fill(bar, NAVY_DEEP, NAVY_LIGHT, angle=0)
    set_no_line(bar)

    # Amber accent stripe under the bar
    stripe = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE,
                                     0, TITLE_BAR_H, SLIDE_W, Inches(0.06))
    set_solid_fill(stripe, AMBER)
    set_no_line(stripe)

    # Decorative angled wedge on the right of the title bar
    wedge = slide.shapes.add_shape(MSO_SHAPE.RIGHT_TRIANGLE,
                                    SLIDE_W - Inches(1.6), 0,
                                    Inches(1.6), TITLE_BAR_H)
    set_solid_fill(wedge, NAVY_DEEP)
    set_no_line(wedge)

    # Eyebrow label (small caps style)
    if eyebrow:
        eb = slide.shapes.add_textbox(Inches(0.55), Inches(0.15),
                                       Inches(8.0), Inches(0.3))
        add_text(eb, eyebrow.upper(), size=11, bold=True, color=AMBER,
                 align=PP_ALIGN.LEFT, anchor=MSO_ANCHOR.TOP)

    # Title text
    tt = slide.shapes.add_textbox(Inches(0.55), Inches(0.4),
                                   Inches(11.5), Inches(0.65))
    add_text(tt, title, size=26, bold=True, color=WHITE,
             align=PP_ALIGN.LEFT, anchor=MSO_ANCHOR.MIDDLE)


def add_side_accent(slide):
    bar = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE,
                                  0, TITLE_BAR_H + Inches(0.06),
                                  ACCENT_BAR_W,
                                  SLIDE_H - TITLE_BAR_H - Inches(0.06) - FOOTER_H)
    set_gradient_fill(bar, NAVY_LIGHT, TEAL, angle=90)
    set_no_line(bar)


def add_footer(slide, slide_num, total):
    # Divider line
    line = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE,
                                   Inches(0.4), SLIDE_H - FOOTER_H + Inches(0.02),
                                   Inches(12.5), Inches(0.012))
    set_solid_fill(line, GRAY_LIGHT)
    set_no_line(line)

    fl = slide.shapes.add_textbox(Inches(0.4), SLIDE_H - FOOTER_H,
                                   Inches(9.0), FOOTER_H)
    add_text(fl,
             "iScan-CRDMS  •  Municipal Civil Registrar's Office, Municipality of Baggao",
             size=9, color=GRAY_MID, align=PP_ALIGN.LEFT)

    fr = slide.shapes.add_textbox(Inches(11.4), SLIDE_H - FOOTER_H,
                                   Inches(1.7), FOOTER_H)
    add_text(fr, f"{slide_num}  /  {total}",
             size=9, bold=True, color=NAVY, align=PP_ALIGN.RIGHT)


# ----------------------------------------------------------------------------
# Content blocks
# ----------------------------------------------------------------------------

def add_narrative(slide, paragraph, *, with_screenshot=False):
    width = NARRATIVE_W_WITH_SHOT if with_screenshot else NARRATIVE_W_FULL
    box = slide.shapes.add_textbox(NARRATIVE_LEFT, NARRATIVE_TOP,
                                    width, NARRATIVE_H)
    tf = box.text_frame
    tf.word_wrap = True
    tf.margin_left = Inches(0.05)
    p = tf.paragraphs[0]
    p.alignment = PP_ALIGN.LEFT
    p.line_spacing = 1.25
    run = p.add_run()
    run.text = paragraph
    run.font.name = "Calibri"
    run.font.size = Pt(13)
    run.font.italic = True
    run.font.color.rgb = BODY_TEXT


def add_bullets(slide, bullets, *, with_screenshot=False, size=14,
                line_spacing=1.2):
    width = NARRATIVE_W_WITH_SHOT if with_screenshot else NARRATIVE_W_FULL
    box = slide.shapes.add_textbox(NARRATIVE_LEFT, BULLETS_TOP,
                                    width, BULLETS_H)
    tf = box.text_frame
    tf.word_wrap = True
    tf.margin_left = Inches(0.05)
    tf.margin_top = Inches(0.05)
    for i, item in enumerate(bullets):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.alignment = PP_ALIGN.LEFT
        p.space_after = Pt(5)
        p.line_spacing = line_spacing

        # Bullet glyph
        bullet_run = p.add_run()
        bullet_run.text = "▸  "
        bullet_run.font.name = "Calibri"
        bullet_run.font.size = Pt(size)
        bullet_run.font.bold = True
        bullet_run.font.color.rgb = AMBER

        # Body, with **bold** markers
        for idx, part in enumerate(item.split("**")):
            if not part:
                continue
            r = p.add_run()
            r.text = part
            r.font.name = "Calibri"
            r.font.size = Pt(size)
            r.font.color.rgb = DARK_TEXT
            r.font.bold = (idx % 2 == 1)


def add_metric_chip(slide, value, label, *, x_offset=0):
    """A small navy chip in the upper-right of the content area, used when a
    slide has a headline number to flaunt."""
    left = Inches(11.0) + Emu(x_offset)
    top = Inches(1.28)
    chip = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE,
                                   left, top, Inches(1.85), Inches(1.05))
    set_solid_fill(chip, NAVY)
    set_gradient_fill(chip, NAVY, TEAL, angle=135)
    set_no_line(chip)

    tf = chip.text_frame
    tf.word_wrap = True
    tf.vertical_anchor = MSO_ANCHOR.MIDDLE
    tf.margin_top = Inches(0.05)
    tf.margin_bottom = Inches(0.05)

    p1 = tf.paragraphs[0]
    p1.alignment = PP_ALIGN.CENTER
    r1 = p1.add_run()
    r1.text = value
    r1.font.name = "Calibri"
    r1.font.size = Pt(22)
    r1.font.bold = True
    r1.font.color.rgb = AMBER

    p2 = tf.add_paragraph()
    p2.alignment = PP_ALIGN.CENTER
    r2 = p2.add_run()
    r2.text = label
    r2.font.name = "Calibri"
    r2.font.size = Pt(9)
    r2.font.color.rgb = WHITE


def add_callout(slide, text):
    box = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE,
                                  CALLOUT_LEFT, CALLOUT_TOP, CALLOUT_W, CALLOUT_H)
    set_solid_fill(box, AMBER_FILL)
    set_line(box, AMBER_BORDER, width_pt=1.5)

    # Left amber square accent
    accent = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE,
                                     CALLOUT_LEFT, CALLOUT_TOP,
                                     Inches(0.18), CALLOUT_H)
    set_solid_fill(accent, AMBER_BORDER)
    set_no_line(accent)

    tf = box.text_frame
    tf.word_wrap = True
    tf.vertical_anchor = MSO_ANCHOR.MIDDLE
    tf.margin_left = Inches(0.35)
    tf.margin_right = Inches(0.25)
    p = tf.paragraphs[0]
    p.alignment = PP_ALIGN.LEFT

    label = p.add_run()
    label.text = "In plain terms:   "
    label.font.name = "Calibri"
    label.font.size = Pt(13)
    label.font.bold = True
    label.font.color.rgb = AMBER_TEXT

    body = p.add_run()
    body.text = text
    body.font.name = "Calibri"
    body.font.size = Pt(13)
    body.font.color.rgb = DARK_TEXT


def add_screenshot_placeholder(slide, caption):
    # Decorative frame
    frame = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE,
                                    SHOT_LEFT - Inches(0.06),
                                    SHOT_TOP - Inches(0.06),
                                    SHOT_W + Inches(0.12),
                                    SHOT_H + Inches(0.12))
    set_solid_fill(frame, NAVY)
    set_no_line(frame)

    box = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE,
                                  SHOT_LEFT, SHOT_TOP, SHOT_W, SHOT_H)
    set_solid_fill(box, PLACEHOLDER_FILL)
    set_dashed_line(box, PLACEHOLDER_BORDER, width_pt=1.5)

    # Small caption strip on the bottom of the placeholder
    cap = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE,
                                  SHOT_LEFT, SHOT_TOP + SHOT_H - Inches(0.55),
                                  SHOT_W, Inches(0.55))
    set_solid_fill(cap, NAVY)
    set_no_line(cap)
    add_text(cap, caption, size=10, italic=True, color=WHITE,
             align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)

    # Centered placeholder content
    tf = box.text_frame
    tf.word_wrap = True
    tf.vertical_anchor = MSO_ANCHOR.MIDDLE
    tf.margin_left = Inches(0.2)
    tf.margin_right = Inches(0.2)
    p = tf.paragraphs[0]
    p.alignment = PP_ALIGN.CENTER
    p.line_spacing = 1.3

    icon_run = p.add_run()
    icon_run.text = "⌗\n"
    icon_run.font.size = Pt(40)
    icon_run.font.color.rgb = PLACEHOLDER_BORDER

    label_run = p.add_run()
    label_run.text = "\n[ Screenshot Placeholder ]\n"
    label_run.font.name = "Calibri"
    label_run.font.size = Pt(12)
    label_run.font.bold = True
    label_run.font.color.rgb = GRAY_MID

    hint_run = p.add_run()
    hint_run.text = "Right-click  →  Change Picture"
    hint_run.font.name = "Calibri"
    hint_run.font.size = Pt(9)
    hint_run.font.italic = True
    hint_run.font.color.rgb = GRAY_MID


# ----------------------------------------------------------------------------
# Icons (navy circle, white inner shapes)
# ----------------------------------------------------------------------------

def _icon_circle(slide):
    # Outer halo ring
    halo = slide.shapes.add_shape(MSO_SHAPE.OVAL,
                                    ICON_LEFT - Inches(0.07),
                                    ICON_TOP - Inches(0.07),
                                    ICON_SIZE + Inches(0.14),
                                    ICON_SIZE + Inches(0.14))
    set_solid_fill(halo, AMBER_FILL)
    set_no_line(halo)
    # Inner navy disc
    c = slide.shapes.add_shape(MSO_SHAPE.OVAL,
                                ICON_LEFT, ICON_TOP, ICON_SIZE, ICON_SIZE)
    set_solid_fill(c, NAVY)
    set_gradient_fill(c, NAVY_DEEP, TEAL, angle=135)
    set_no_line(c)
    return ICON_LEFT, ICON_TOP


def _rect(slide, l, t, w, h, fill=WHITE, line=None):
    s = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, l, t, w, h)
    set_solid_fill(s, fill)
    if line is None:
        set_no_line(s)
    else:
        set_line(s, line, width_pt=1)
    return s


def _oval(slide, l, t, w, h, fill=WHITE):
    s = slide.shapes.add_shape(MSO_SHAPE.OVAL, l, t, w, h)
    set_solid_fill(s, fill)
    set_no_line(s)
    return s


def _text_glyph(slide, l, t, w, h, text, size=20, color=WHITE, bold=True):
    box = slide.shapes.add_textbox(l, t, w, h)
    add_text(box, text, size=size, color=color, bold=bold,
             align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)


def icon_monogram(slide):
    _icon_circle(slide)
    _text_glyph(slide, ICON_LEFT, ICON_TOP, ICON_SIZE, ICON_SIZE, "iS", size=32)


def icon_summary(slide):
    _icon_circle(slide)
    _text_glyph(slide, ICON_LEFT, ICON_TOP, ICON_SIZE, ICON_SIZE, "§", size=36)


def icon_warning(slide):
    _icon_circle(slide)
    _text_glyph(slide, ICON_LEFT, ICON_TOP, ICON_SIZE, ICON_SIZE, "!", size=44)


def icon_target(slide):
    l, t = _icon_circle(slide)
    _oval(slide, l + Inches(0.18), t + Inches(0.18),
          Inches(0.64), Inches(0.64), fill=WHITE)
    _oval(slide, l + Inches(0.34), t + Inches(0.34),
          Inches(0.32), Inches(0.32), fill=NAVY)
    _oval(slide, l + Inches(0.43), t + Inches(0.43),
          Inches(0.14), Inches(0.14), fill=AMBER)


def icon_grid(slide):
    l, t = _icon_circle(slide)
    gap = Inches(0.05)
    sq = Inches(0.24)
    base_l = l + Inches(0.22)
    base_t = t + Inches(0.22)
    for r in range(2):
        for c in range(2):
            fill = AMBER if (r + c) == 1 else WHITE
            _rect(slide,
                  base_l + c * (sq + gap),
                  base_t + r * (sq + gap),
                  sq, sq, fill=fill)


def icon_scanner(slide):
    l, t = _icon_circle(slide)
    _rect(slide, l + Inches(0.14), t + Inches(0.55),
          Inches(0.72), Inches(0.22), fill=WHITE)
    _rect(slide, l + Inches(0.28), t + Inches(0.18),
          Inches(0.44), Inches(0.4), fill=WHITE)
    for y in [0.27, 0.36, 0.46]:
        _rect(slide, l + Inches(0.35), t + Inches(y),
              Inches(0.3), Inches(0.03), fill=NAVY)


def icon_ocr(slide):
    l, t = _icon_circle(slide)
    _rect(slide, l + Inches(0.18), t + Inches(0.17),
          Inches(0.45), Inches(0.7), fill=WHITE)
    for y in [0.28, 0.4, 0.52, 0.64, 0.76]:
        _rect(slide, l + Inches(0.24), t + Inches(y),
              Inches(0.33), Inches(0.03), fill=NAVY)
    _oval(slide, l + Inches(0.5), t + Inches(0.46),
          Inches(0.4), Inches(0.4), fill=AMBER)
    _oval(slide, l + Inches(0.55), t + Inches(0.51),
          Inches(0.3), Inches(0.3), fill=WHITE)


def icon_search(slide):
    l, t = _icon_circle(slide)
    _oval(slide, l + Inches(0.18), t + Inches(0.18),
          Inches(0.5), Inches(0.5), fill=WHITE)
    _oval(slide, l + Inches(0.26), t + Inches(0.26),
          Inches(0.34), Inches(0.34), fill=NAVY)
    handle = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE,
                                     l + Inches(0.6), t + Inches(0.6),
                                     Inches(0.26), Inches(0.08))
    set_solid_fill(handle, AMBER)
    set_no_line(handle)
    handle.rotation = 45


def icon_duplicates(slide):
    l, t = _icon_circle(slide)
    _rect(slide, l + Inches(0.18), t + Inches(0.18),
          Inches(0.45), Inches(0.55), fill=WHITE)
    _rect(slide, l + Inches(0.34), t + Inches(0.32),
          Inches(0.45), Inches(0.55), fill=AMBER, line=WHITE)


def icon_workflow(slide):
    l, t = _icon_circle(slide)
    cy = t + Inches(0.46)
    line = slide.shapes.add_connector(1,
                                       l + Inches(0.18), cy + Inches(0.06),
                                       l + Inches(0.82), cy + Inches(0.06))
    line.line.color.rgb = WHITE
    line.line.width = Pt(2)
    xs = [0.15, 0.32, 0.49, 0.66, 0.83]
    for i, x in enumerate(xs):
        fill = AMBER if i == 4 else WHITE
        _oval(slide, l + Inches(x), cy, Inches(0.12), Inches(0.12), fill=fill)


def icon_batch(slide):
    l, t = _icon_circle(slide)
    for i, y in enumerate([0.65, 0.52, 0.39]):
        fill = AMBER if i == 2 else WHITE
        _rect(slide, l + Inches(0.22), t + Inches(y),
              Inches(0.56), Inches(0.1), fill=fill)
    arrow = slide.shapes.add_shape(MSO_SHAPE.UP_ARROW,
                                    l + Inches(0.43), t + Inches(0.13),
                                    Inches(0.18), Inches(0.2))
    set_solid_fill(arrow, AMBER)
    set_no_line(arrow)


def icon_analytics(slide):
    l, t = _icon_circle(slide)
    heights = [0.22, 0.36, 0.5]
    base_t = t + Inches(0.85)
    colors = [WHITE, WHITE, AMBER]
    for i, h in enumerate(heights):
        _rect(slide,
              l + Inches(0.25 + i * 0.2),
              base_t - Inches(h),
              Inches(0.14), Inches(h),
              fill=colors[i])


def icon_ra9048(slide):
    l, t = _icon_circle(slide)
    _rect(slide, l + Inches(0.24), t + Inches(0.18),
          Inches(0.5), Inches(0.7), fill=WHITE)
    star = slide.shapes.add_shape(MSO_SHAPE.STAR_5_POINT,
                                   l + Inches(0.36), t + Inches(0.45),
                                   Inches(0.27), Inches(0.27))
    set_solid_fill(star, AMBER)
    set_no_line(star)


def icon_shield(slide):
    l, t = _icon_circle(slide)
    sh = slide.shapes.add_shape(MSO_SHAPE.PENTAGON,
                                 l + Inches(0.24), t + Inches(0.18),
                                 Inches(0.5), Inches(0.66))
    set_solid_fill(sh, WHITE)
    set_no_line(sh)
    sh.rotation = 90
    _text_glyph(slide, ICON_LEFT, ICON_TOP, ICON_SIZE, ICON_SIZE,
                "+", size=22, color=NAVY)


def icon_lock(slide):
    l, t = _icon_circle(slide)
    sh = slide.shapes.add_shape(MSO_SHAPE.BLOCK_ARC,
                                 l + Inches(0.3), t + Inches(0.18),
                                 Inches(0.42), Inches(0.38))
    set_solid_fill(sh, WHITE)
    set_no_line(sh)
    _rect(slide, l + Inches(0.24), t + Inches(0.46),
          Inches(0.54), Inches(0.42), fill=WHITE)
    _rect(slide, l + Inches(0.47), t + Inches(0.58),
          Inches(0.07), Inches(0.18), fill=AMBER)


def icon_device(slide):
    l, t = _icon_circle(slide)
    _rect(slide, l + Inches(0.18), t + Inches(0.24),
          Inches(0.64), Inches(0.42), fill=WHITE)
    _rect(slide, l + Inches(0.13), t + Inches(0.68),
          Inches(0.74), Inches(0.07), fill=WHITE)
    _text_glyph(slide, l + Inches(0.18), t + Inches(0.24),
                Inches(0.64), Inches(0.42), "✓",
                size=22, color=NAVY)


def icon_people(slide):
    l, t = _icon_circle(slide)
    for i, x in enumerate([0.14, 0.36, 0.58]):
        size = 0.2 + i * 0.04
        _oval(slide, l + Inches(x + 0.04), t + Inches(0.28 - i * 0.04),
              Inches(0.18), Inches(0.18), fill=WHITE)
        _rect(slide, l + Inches(x), t + Inches(0.5 - i * 0.04),
              Inches(size), Inches(0.3 + i * 0.04), fill=WHITE)


def icon_audit(slide):
    l, t = _icon_circle(slide)
    _rect(slide, l + Inches(0.23), t + Inches(0.18),
          Inches(0.52), Inches(0.7), fill=WHITE)
    for y in [0.3, 0.42, 0.54, 0.66]:
        color = AMBER if y == 0.66 else NAVY
        _rect(slide, l + Inches(0.29), t + Inches(y),
              Inches(0.4), Inches(0.04), fill=color)


def icon_backup(slide):
    l, t = _icon_circle(slide)
    _oval(slide, l + Inches(0.2), t + Inches(0.28),
          Inches(0.6), Inches(0.16), fill=WHITE)
    _rect(slide, l + Inches(0.2), t + Inches(0.36),
          Inches(0.6), Inches(0.35), fill=WHITE)
    _oval(slide, l + Inches(0.2), t + Inches(0.63),
          Inches(0.6), Inches(0.16), fill=WHITE)
    _text_glyph(slide, ICON_LEFT, ICON_TOP, ICON_SIZE, ICON_SIZE,
                "✓", size=20, color=NAVY)


def icon_web_shield(slide):
    l, t = _icon_circle(slide)
    sh = slide.shapes.add_shape(MSO_SHAPE.PENTAGON,
                                 l + Inches(0.24), t + Inches(0.18),
                                 Inches(0.5), Inches(0.66))
    set_solid_fill(sh, WHITE)
    set_no_line(sh)
    sh.rotation = 90
    for y in [0.36, 0.5, 0.64]:
        color = AMBER if y == 0.5 else NAVY
        _rect(slide, l + Inches(0.3), t + Inches(y),
              Inches(0.38), Inches(0.025), fill=color)


def icon_deployment(slide):
    l, t = _icon_circle(slide)
    _oval(slide, l + Inches(0.14), t + Inches(0.16),
          Inches(0.7), Inches(0.3), fill=WHITE)
    _rect(slide, l + Inches(0.22), t + Inches(0.5),
          Inches(0.56), Inches(0.38), fill=WHITE)
    for i, y in enumerate([0.55, 0.63, 0.71, 0.79]):
        color = AMBER if i == 0 else NAVY
        _rect(slide, l + Inches(0.27), t + Inches(y),
              Inches(0.46), Inches(0.04), fill=color)


def icon_check(slide):
    _icon_circle(slide)
    _text_glyph(slide, ICON_LEFT, ICON_TOP, ICON_SIZE, ICON_SIZE, "✓", size=46)


# ----------------------------------------------------------------------------
# Special slides
# ----------------------------------------------------------------------------

def make_title_slide(slide):
    # Full-bleed gradient background
    bg = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, SLIDE_W, SLIDE_H)
    set_solid_fill(bg, NAVY)
    set_gradient_fill(bg, NAVY_DEEP, NAVY_LIGHT, angle=45)
    set_no_line(bg)

    # Large decorative ring (top right)
    ring = slide.shapes.add_shape(MSO_SHAPE.OVAL,
                                    SLIDE_W - Inches(3.5), Inches(-2.0),
                                    Inches(5.0), Inches(5.0))
    set_no_fill(ring)
    set_line(ring, AMBER, width_pt=2.0)
    ring.fill.background()

    # Smaller filled circle (bottom left)
    dot = slide.shapes.add_shape(MSO_SHAPE.OVAL,
                                   Inches(-1.0), Inches(5.5),
                                   Inches(2.5), Inches(2.5))
    set_solid_fill(dot, TEAL)
    set_no_line(dot)

    # Amber accent stripe in the middle
    stripe = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE,
                                     Inches(0.8), Inches(3.92),
                                     Inches(1.2), Inches(0.08))
    set_solid_fill(stripe, AMBER)
    set_no_line(stripe)

    # Eyebrow
    eb = slide.shapes.add_textbox(Inches(0.8), Inches(1.45),
                                    Inches(11.0), Inches(0.4))
    add_text(eb, "MUNICIPAL CIVIL REGISTRAR'S OFFICE  •  MUNICIPALITY OF BAGGAO",
             size=13, bold=True, color=AMBER, align=PP_ALIGN.LEFT)

    # Title
    title = slide.shapes.add_textbox(Inches(0.8), Inches(1.85),
                                       Inches(11.7), Inches(1.4))
    add_text(title, "iScan-CRDMS", size=72, bold=True, color=WHITE,
             align=PP_ALIGN.LEFT, anchor=MSO_ANCHOR.BOTTOM)

    sub = slide.shapes.add_textbox(Inches(0.8), Inches(4.05),
                                     Inches(11.7), Inches(0.8))
    add_text(sub, "Civil Registry Records Management System",
             size=26, color=WHITE, align=PP_ALIGN.LEFT)

    purpose = slide.shapes.add_textbox(Inches(0.8), Inches(4.85),
                                         Inches(11.7), Inches(0.6))
    add_text(purpose,
             "A presentation on relevant features and security safeguards for executive review",
             size=14, italic=True, color=WHITE, align=PP_ALIGN.LEFT)

    led = slide.shapes.add_textbox(Inches(0.8), Inches(5.65),
                                     Inches(11.7), Inches(0.5))
    add_text(led, "Under the leadership of Sir Atanacio G. Tungpalan, MCR",
             size=14, color=WHITE, align=PP_ALIGN.LEFT)

    # Bottom strip
    strip = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE,
                                     0, SLIDE_H - Inches(0.7),
                                     SLIDE_W, Inches(0.06))
    set_solid_fill(strip, AMBER)
    set_no_line(strip)

    footer = slide.shapes.add_textbox(Inches(0.8), SLIDE_H - Inches(0.6),
                                        Inches(11.7), Inches(0.5))
    add_text(footer,
             "Presenter:  ___________________     Date:  ___________________     https://iscan.cdrms.online",
             size=11, color=WHITE, align=PP_ALIGN.LEFT)


def make_conclusion_slide(slide):
    add_slide_background(slide)
    add_title_bar(slide, "Conclusion", eyebrow="A Modern Foundation, Built To Last")
    add_side_accent(slide)
    icon_check(slide)

    narrative = (
        "iScan-CRDMS replaces a fragile paper-based workflow with a secure, "
        "auditable, and resilient digital platform — built specifically for "
        "the Municipality of Baggao and operated entirely on the office's own "
        "infrastructure. It delivers measurable gains today and a durable "
        "foundation for the decades of civil registry service to come."
    )
    add_narrative(slide, narrative, with_screenshot=False)

    bullets = [
        "**Faster service.** Records retrieved in seconds; data entry reduced by 83%.",
        "**Fewer errors.** OCR confidence scoring, automated double-registration detection, and structured validation.",
        "**Complete accountability.** Every action attributable to a specific user, device, and moment.",
        "**Full data sovereignty.** Records stay on the LGU's own NAS — not in a third-party cloud.",
    ]
    add_bullets(slide, bullets, with_screenshot=False, size=15)

    thanks = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE,
                                     CALLOUT_LEFT, CALLOUT_TOP,
                                     CALLOUT_W, CALLOUT_H)
    set_solid_fill(thanks, NAVY)
    set_gradient_fill(thanks, NAVY_DEEP, TEAL, angle=0)
    set_no_line(thanks)
    add_text(thanks, "Thank you.   Questions are most welcome.",
             size=20, bold=True, color=WHITE,
             align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)


def make_content_slide(slide, *, title, eyebrow, icon_fn, narrative,
                       bullets, callout, screenshot_caption=None, metric=None):
    add_slide_background(slide)
    add_title_bar(slide, title, eyebrow=eyebrow)
    add_side_accent(slide)
    icon_fn(slide)

    has_shot = bool(screenshot_caption)
    add_narrative(slide, narrative, with_screenshot=has_shot)
    add_bullets(slide, bullets, with_screenshot=has_shot)

    if has_shot:
        add_screenshot_placeholder(slide, screenshot_caption)
    elif metric:
        add_metric_chip(slide, metric[0], metric[1])

    add_callout(slide, callout)


# ----------------------------------------------------------------------------
# Slide content
# ----------------------------------------------------------------------------

SLIDES = [
    # 1 ------------------------------------------------------------------
    {"kind": "title"},

    # 2 ------------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Executive Summary",
     "title": "A Locally Hosted, Sovereign Records Platform",
     "icon": icon_summary,
     "narrative":
        "iScan-CRDMS is the digital records management platform of the Municipal "
        "Civil Registrar's Office of Baggao. It digitizes and safeguards the four "
        "civil registry document types — Live Birth, Marriage, Death, and Marriage "
        "License — together with petitions filed under Republic Act No. 9048. The "
        "entire system runs on the municipality's own Synology DS925+ NAS, ensuring "
        "that the records belonging to Baggao's residents never leave the office.",
     "bullets": [
         "Four certificate modules plus a dedicated **RA 9048 petition workflow.**",
         "Built under the leadership of **Sir Atanacio G. Tungpalan, MCR.**",
         "Hosted on the office's own **Synology DS925+ NAS** — no third-party cloud.",
         "Reachable at **https://iscan.cdrms.online** through a secure Cloudflare Tunnel.",
     ],
     "callout":
        "The municipality's records stay with the municipality — secured, "
        "searchable, and always available."},

    # 3 ------------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Context",
     "title": "The Operational Problem We Are Addressing",
     "icon": icon_warning,
     "narrative":
        "For decades, civil registry work in Baggao has depended almost entirely "
        "on paper logbooks, loose certificates, and steel filing cabinets. This "
        "traditional approach exposes irreplaceable documents to physical "
        "deterioration and catastrophic loss, slows down every citizen request, "
        "makes duplicate registrations almost impossible to detect, and leaves "
        "the office without any reliable trail of who edited what — let alone the "
        "ability to produce timely management information.",
     "bullets": [
         "Paper records are vulnerable to **fire, flooding, pest damage, fading, and misfiling.**",
         "Manual retrieval of a single certificate can take hours.",
         "Duplicates and clerical errors are difficult to detect once filed.",
         "Accountability is limited — paper offers **no audit trail.**",
     ],
     "callout":
        "The current process is fragile, slow, and offers no way to prove who "
        "changed what."},

    # 4 ------------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Rationale",
     "title": "Why iScan-CRDMS Was Built",
     "icon": icon_target,
     "narrative":
        "Civil registry data is the foundation of every citizen's legal identity — "
        "it determines citizenship, inheritance, marital status, parental "
        "authority, and access to government services. The platform was conceived "
        "to protect these records with the same seriousness a bank protects "
        "money, while remaining fully under the LGU's own control. It is designed "
        "to bring instant retrieval, modern security, and disaster-resilient "
        "storage into the registrar's office without ever surrendering data to "
        "an external commercial cloud.",
     "bullets": [
         "Protect the records that define **citizenship, inheritance, and legal identity.**",
         "Deliver **instant retrieval, modern audit visibility, and disaster resilience.**",
         "Preserve full **data sovereignty** — records remain on LGU hardware.",
         "Uphold the registrar's responsibility to the residents of Baggao.",
     ],
     "callout":
        "The same records, in safer hands, with better tools."},

    # 5 ------------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "At a Glance",
     "title": "System Overview",
     "icon": icon_grid,
     "narrative":
        "iScan-CRDMS is a single, unified web application that consolidates every "
        "civil registry activity into one platform. It pairs purpose-built data "
        "entry forms with server-side OCR, integrated scanner hardware, a "
        "structured approval workflow, and a full audit trail — all engineered "
        "to fit the day-to-day reality of a Philippine LGU's civil registry "
        "office.",
     "bullets": [
         "Four certificate modules: **Live Birth, Marriage, Death, Marriage License.**",
         "Dedicated **RA 9048 petition module** (CCE and CFN workflows).",
         "**19 user-facing pages • 9 admin pages • 35+ secured APIs.**",
         "Server-side OCR, scanner integration, workflow engine, complete audit trail.",
     ],
     "callout":
        "One complete platform for everything the Civil Registrar's Office does."},

    # 6 ------------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Feature",
     "title": "Integrated Document Scanning",
     "icon": icon_scanner,
     "narrative":
        "iScan-CRDMS speaks directly to the office's existing Epson DS-530 II "
        "flatbed scanner through a dedicated Python-Flask microservice. Staff "
        "place a certificate on the scanner and click a single button inside "
        "the data entry form — the resulting multi-page PDF is produced, "
        "uploaded, and attached to the record without ever leaving the browser. "
        "There is no separate scanning application to launch, no temporary files "
        "to manage, and no opportunity for a scan to be lost between systems.",
     "bullets": [
         "Direct browser-to-scanner integration with the **Epson DS-530 II.**",
         "Dedicated Python-Flask scanner microservice on port 18622.",
         "Scans saved as multi-page PDFs, attached to the record automatically.",
         "Uses the office's existing hardware — no additional purchase required.",
     ],
     "callout":
        "Scan and attach in one step — without leaving the iScan screen.",
     "shot": "Scanner panel inside the certificate form"},

    # 7 ------------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Feature",
     "title": "OCR-Assisted Data Entry",
     "icon": icon_ocr,
     "narrative":
        "The system's OCR pipeline — powered by Tesseract — reads each scanned "
        "certificate and automatically fills the data entry form with the values "
        "it recognises: names, sex, dates, places, registry numbers, and more. "
        "Every extracted field carries a confidence score so the encoder knows "
        "exactly which values to verify and which to accept on sight. Results "
        "are cached by SHA-256 file hash, so re-opening the same PDF returns "
        "results instantly without re-processing.",
     "bullets": [
         "Tesseract OCR **automatically fills the data entry form.**",
         "**Sixteen pre-mapped fields** per document type, with confidence scoring.",
         "Results cached by SHA-256 hash — **instant re-use** on the same PDF.",
         "Cuts data entry from **10–15 minutes to 2–3 minutes per record.**",
     ],
     "callout":
        "The system reads the certificate. The encoder only verifies.",
     "shot": "OCR extracted-data panel with confidence scores"},

    # 8 ------------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Feature",
     "title": "Advanced Search & Retrieval",
     "icon": icon_search,
     "narrative":
        "Where the manual logbook required linear scanning of entire volumes, "
        "iScan-CRDMS retrieves any record in seconds. A two-pass search "
        "algorithm first attempts a strict multi-token match across all "
        "indexed fields — name, registry number, date, barangay, parent — "
        "and falls back to fuzzy matching only if no exact result exists. "
        "A side-by-side PDF Comparison Viewer then lets the registrar verify "
        "the form data against the original scanned certificate without "
        "leaving the browser.",
     "bullets": [
         "**Two-pass algorithm:** strict multi-token match, then fuzzy fallback.",
         "Search by **name, registry number, date range, barangay, or parent name.**",
         "**PDF Comparison Viewer** for side-by-side verification.",
         "**92% faster retrieval** than manual logbook search.",
     ],
     "callout":
        "Any record, found in seconds.",
     "shot": "Advanced search results page"},

    # 9 ------------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Feature",
     "title": "Double-Registration Detection",
     "icon": icon_duplicates,
     "narrative":
        "The same vital event is sometimes registered more than once — through "
        "clerical error, late filing, family relocation, or a citizen "
        "re-applying without disclosing a prior registration. Such duplicates "
        "are nearly impossible to spot in a paper system, yet they create "
        "serious legal complications when discovered years later. iScan-CRDMS "
        "automatically flags probable duplicates, scores how closely two "
        "records match, highlights field-level discrepancies as critical or "
        "minor, and tracks the correction process from detection to resolution.",
     "bullets": [
         "Automatic detection of probable **duplicate registrations** of the same event.",
         "**Match scoring** and field-level discrepancy classification.",
         "Correction status tracked from detection through resolution.",
         "Backed by a dedicated `record_links` table for traceability.",
     ],
     "callout":
        "Surfaces duplicate records that paper-based filing would hide for years.",
     "shot": "Side-by-side double-registration comparison modal"},

    # 10 -----------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Feature",
     "title": "Six-State Approval Workflow",
     "icon": icon_workflow,
     "narrative":
        "Every certificate moves through a structured lifecycle — Draft, "
        "Pending Review, Verified, Approved, and Archived — with Reject and "
        "Reopen branches for records that require correction. Each transition "
        "is recorded against the responsible user with a timestamp and "
        "optional notes, producing a clear chain of accountability from "
        "initial encoding through final archival. No record reaches the "
        "archive without first being reviewed and approved by the appropriate "
        "personnel.",
     "bullets": [
         "States: **Draft → Pending Review → Verified → Approved → Archived.**",
         "Reject and Reopen transitions are tracked with **notes and timestamps.**",
         "Every transition recorded against the responsible user.",
         "Per-record **quality score (0–100%)** for review and oversight.",
     ],
     "callout":
        "No record is archived until it has been reviewed and approved — and "
        "every approval is on the record.",
     "shot": "Workflow dashboard with state counts"},

    # 11 -----------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Feature",
     "title": "Batch Upload & Historical Digitization",
     "icon": icon_batch,
     "narrative":
        "Large-scale digitization of legacy paper records is one of the heaviest "
        "tasks any civil registry office faces. iScan-CRDMS makes it manageable "
        "with a drag-and-drop multi-file upload interface that accepts entire "
        "batches at once, optionally runs OCR and validation automatically, and "
        "reports progress in real time. Each batch is named for accountability "
        "so the office can later audit exactly which volumes were digitized, by "
        "whom, and on which date.",
     "bullets": [
         "**Drag-and-drop multi-file upload** with auto-OCR and auto-validate options.",
         "**Real-time progress tracking** with per-file success and failure indicators.",
         "Batch naming and tracking for accountability and later auditing.",
         "Designed for digitizing **years of backlogged paper records.**",
     ],
     "callout":
        "Years of backlogged records can be digitized in days, not months.",
     "shot": "Batch upload page mid-upload"},

    # 12 -----------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Feature",
     "title": "Analytics & Reporting",
     "icon": icon_analytics,
     "narrative":
        "Without digital records, the registrar's office cannot easily produce "
        "trend reports, demographic summaries, or staff productivity metrics. "
        "iScan-CRDMS delivers all of these through a live dashboard built on "
        "Chart.js: monthly and year-over-year birth, marriage, and death trends; "
        "demographic breakdowns by gender, citizenship, and barangay; "
        "late-registration flags; and an encoder leaderboard that ranks users "
        "by recorded CREATE actions. Filtered records can be exported to XLS or "
        "CSV with a single click.",
     "bullets": [
         "Live dashboards with **monthly and year-over-year trends.**",
         "Demographics, gender distribution, citizenship, and late-registration flags.",
         "**Encoder leaderboard** ranking staff by recorded actions.",
         "One-click export of filtered records to **XLS or CSV.**",
     ],
     "callout":
        "The registrar sees performance and trends without producing a single "
        "tally sheet by hand.",
     "shot": "Admin dashboard with monthly trend charts"},

    # 13 -----------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Feature",
     "title": "RA 9048 Petition Module",
     "icon": icon_ra9048,
     "narrative":
        "Republic Act 9048 petitions for the correction of clerical or "
        "typographical errors — and Republic Act 10172 petitions for changes "
        "to sex or date of birth — are themselves paper-heavy proceedings. The "
        "module automates the drafting of the petition itself, the order for "
        "posting, the certificate of posting, and the certification of proof "
        "of filing, all generated from official DOCX templates so the wording "
        "remains consistent and PSA-compliant. Each document can be previewed "
        "in the browser before printing.",
     "bullets": [
         "Dedicated workflows for **CCE** (Clerical / Typographical Error) and **CFN** (Change of First Name).",
         "Auto-generates **petition, posting order, certificate of posting, proof of filing.**",
         "Document templates kept in `documents/templates/` — wording stays consistent.",
         "In-browser **PDF preview** before printing.",
     ],
     "callout":
        "Petition paperwork drafted in minutes, not hours — with no clerical drift.",
     "shot": "RA 9048 petition preview"},

    # 14 -----------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Security",
     "title": "A Layered, Defense-in-Depth Approach",
     "icon": icon_shield,
     "narrative":
        "Civil registry data is among the most sensitive personal information "
        "any government office holds. iScan-CRDMS treats security as a "
        "first-class concern, not an afterthought. Twelve distinct security "
        "categories — drawn from established industry best practice — work "
        "together so that if any single layer is bypassed, the next one still "
        "holds. The result is a system where unauthorized access requires "
        "defeating multiple, independent safeguards in sequence.",
     "bullets": [
         "**Twelve distinct security categories** aligned with industry best practice.",
         "**Defense-in-depth design** — every layer is independent of the next.",
         "Covers authentication, authorization, transport, integrity, audit, infrastructure.",
         "Configurable per environment (development vs. production) via `.env` settings.",
     ],
     "callout":
        "Multiple independent locks protect the same data.",
     "metric": ("12", "SECURITY\nLAYERS")},

    # 15 -----------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Security",
     "title": "Login & Account Protection",
     "icon": icon_lock,
     "narrative":
        "User credentials never sit in the database in a form anyone can read. "
        "Passwords are hashed with bcrypt — a deliberately slow, computation-"
        "heavy algorithm that makes password cracking economically infeasible "
        "even if the database is somehow obtained. Repeated failed attempts "
        "are detected by IP-based rate limiting; the offender is locked out "
        "automatically. Active sessions regenerate their identifier every "
        "thirty minutes and self-terminate after an hour of inactivity.",
     "bullets": [
         "Passwords stored with **bcrypt hashing** — irreversible, never plain text.",
         "**Rate limiting:** five attempts per five minutes; fifteen-minute lockout.",
         "Session regeneration every thirty minutes.",
         "Automatic timeout after one hour of inactivity with clean termination.",
     ],
     "callout":
        "Brute-force guessing is mathematically blocked; idle sessions close "
        "themselves.",
     "metric": ("5×", "FAIL-SAFE\nATTEMPTS")},

    # 16 -----------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Security  •  Defining Safeguard",
     "title": "Device Lock — Approved Devices Only",
     "icon": icon_device,
     "narrative":
        "Device Lock is the system's most distinctive safeguard. Even if an "
        "attacker somehow obtains a valid username and password, the login "
        "page itself refuses to load on any device that has not been "
        "pre-approved by an administrator. Each device is fingerprinted from "
        "eleven independent browser and hardware signals — user agent, "
        "platform, screen resolution, GPU renderer, timezone, and more — "
        "hashed together with SHA-256. The check happens before credentials "
        "are even submitted.",
     "bullets": [
         "Only **pre-approved devices** can reach the login page.",
         "Fingerprint derived from **eleven signals**, hashed with SHA-256.",
         "Unregistered devices are blocked **before credentials are checked.**",
         "Admin-managed enrollment, revocation, and full event audit.",
     ],
     "callout":
        "A stolen password is useless on an unknown laptop.",
     "shot": "Admin → Devices management page"},

    # 17 -----------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Security",
     "title": "Role-Based Access Control",
     "icon": icon_people,
     "narrative":
        "Not every staff member needs access to every record or every "
        "function. iScan-CRDMS implements three carefully scoped roles — "
        "Admin, Encoder, and Viewer — with permissions configurable at the "
        "level of individual certificate types. An encoder responsible for "
        "marriages may have no permission to touch death certificates; a "
        "viewer may be allowed to retrieve records but never to modify them. "
        "Delete operations are restricted to administrators alone, and every "
        "API endpoint checks permissions on every call.",
     "bullets": [
         "Three roles: **Admin, Encoder, Viewer.**",
         "Permissions configurable **per certificate type** (view, create, edit, archive).",
         "**Delete operations restricted to administrators only.**",
         "Permission checks enforced on every page and API endpoint.",
     ],
     "callout":
        "Every user sees only what their position requires.",
     "metric": ("3", "ROLES /\nGRANULAR")},

    # 18 -----------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Security",
     "title": "Complete Audit Trail",
     "icon": icon_audit,
     "narrative":
        "Every meaningful action in iScan-CRDMS is captured in an append-only "
        "log. The activity log records every create, update, archive, delete, "
        "login, and logout — along with the user, the action, the IP address, "
        "the device user agent, and the precise timestamp. A separate security "
        "log captures higher-stakes events such as failed logins, CSRF "
        "violations, and device-lock blocks, classified by severity. "
        "Administrators can filter both logs by user, action, date, severity, "
        "and IP from a dedicated viewer.",
     "bullets": [
         "Append-only **activity_logs** — every create, update, archive, delete, login, logout.",
         "Separate **security_logs** with severity: **Low / Medium / High / Critical.**",
         "Each entry records user, action, IP, user agent, and timestamp.",
         "Admin viewer with filtering by user, action, date, severity, IP.",
     ],
     "callout":
        "Every action is traceable to a specific person, device, and moment.",
     "shot": "Activity Logs viewer with filters"},

    # 19 -----------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Security",
     "title": "Data Integrity & Backups",
     "icon": icon_backup,
     "narrative":
        "Every PDF that enters the system is hashed with SHA-256 at the moment "
        "of upload, and that hash becomes its fingerprint. Any subsequent "
        "alteration — accidental or deliberate — is immediately detectable. "
        "The PDF Integrity Scanner can audit the entire archive on demand, "
        "while the PDF Backup Manager automatically retains the previous "
        "version whenever a file is replaced and offers one-click restoration. "
        "Combined with a soft-delete lifecycle, the system makes it virtually "
        "impossible to lose a record permanently by accident.",
     "bullets": [
         "**SHA-256 hashing** on every PDF — instantly detects tampering or corruption.",
         "**PDF Integrity Scanner** audits the entire archive on demand.",
         "**PDF Backup Manager:** automatic backups on replacement, one-click restore.",
         "Soft-delete lifecycle (Active / Archived / Deleted); permanent deletion is admin-only.",
     ],
     "callout":
        "Files cannot be silently altered, and nothing is ever truly lost.",
     "metric": ("SHA-256", "FILE\nINTEGRITY")},

    # 20 -----------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Security",
     "title": "Web-Layer Defenses",
     "icon": icon_web_shield,
     "narrative":
        "Beyond the visible safeguards, the system is hardened against the "
        "common catalogue of web attacks. CSRF tokens are required on every "
        "state-changing request. Database queries use prepared statements "
        "exclusively, eliminating SQL injection at the language level. File "
        "uploads are validated by content type rather than file extension. "
        "Browser-side protections include a strict Content Security Policy, "
        "HSTS, X-Frame-Options, and X-Content-Type-Options headers — invisible "
        "to the user but essential for safe operation.",
     "bullets": [
         "**CSRF tokens** required on every state-changing request.",
         "**Prepared SQL statements** throughout — SQL injection blocked by design.",
         "**MIME-validated** file uploads (PDF only); path-traversal protection.",
         "Strict **Content Security Policy, HSTS, X-Frame-Options, X-Content-Type-Options.**",
     ],
     "callout":
        "The system is hardened against the standard catalogue of web attacks.",
     "metric": ("4+", "HARDENING\nLAYERS")},

    # 21 -----------------------------------------------------------------
    {"kind": "content",
     "eyebrow": "Deployment",
     "title": "On-Premises Sovereignty",
     "icon": icon_deployment,
     "narrative":
        "iScan-CRDMS runs on a Synology DS925+ NAS installed inside the "
        "municipal hall itself. Public access is provided through a Cloudflare "
        "Tunnel — an outbound-only connection that requires no inbound port "
        "forwarding and exposes no router ports to the public internet. "
        "Daily automated database backups run at 02:00; the code base "
        "synchronizes itself with the central repository every hour; and all "
        "vendor assets are stored locally so the system remains fully "
        "operational even when internet connectivity is lost.",
     "bullets": [
         "Hosted on a **Synology DS925+ NAS** inside the municipal hall.",
         "**Cloudflare Tunnel** (https://iscan.cdrms.online) — outbound-only, no exposed ports.",
         "**Daily database backup at 02:00**; hourly code update from the repository.",
         "**Offline-capable:** functions during internet outages with local assets.",
     ],
     "callout":
        "The data lives in this office. Authorized staff can still reach it "
        "securely from anywhere.",
     "metric": ("100%", "DATA\nSOVEREIGN")},

    # 22 -----------------------------------------------------------------
    {"kind": "conclusion"},
]


# ----------------------------------------------------------------------------
# Build
# ----------------------------------------------------------------------------

def build_presentation(output_path):
    prs = Presentation()
    prs.slide_width = SLIDE_W
    prs.slide_height = SLIDE_H

    blank_layout = prs.slide_layouts[6]
    total = len(SLIDES)

    for idx, spec in enumerate(SLIDES, start=1):
        slide = prs.slides.add_slide(blank_layout)
        kind = spec["kind"]

        if kind == "title":
            make_title_slide(slide)
        elif kind == "conclusion":
            make_conclusion_slide(slide)
            add_footer(slide, idx, total)
        else:
            make_content_slide(
                slide,
                title=spec["title"],
                eyebrow=spec["eyebrow"],
                icon_fn=spec["icon"],
                narrative=spec["narrative"],
                bullets=spec["bullets"],
                callout=spec["callout"],
                screenshot_caption=spec.get("shot"),
                metric=spec.get("metric"),
            )
            add_footer(slide, idx, total)

    prs.save(output_path)
    return output_path


if __name__ == "__main__":
    here = os.path.dirname(os.path.abspath(__file__))
    out_dir = os.path.normpath(os.path.join(here, "..", "documents"))
    os.makedirs(out_dir, exist_ok=True)
    out_path = os.path.join(out_dir, "iScan_Executive_Presentation.pptx")
    try:
        build_presentation(out_path)
        print(f"Presentation written to: {out_path}")
    except PermissionError:
        # File likely open in PowerPoint — fall back to a versioned name so
        # the user does not have to close their viewer.
        alt = os.path.join(out_dir, "iScan_Executive_Presentation_v2.pptx")
        build_presentation(alt)
        print(f"Primary file was locked. Wrote new version to: {alt}")
