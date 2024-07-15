;+---+---+---+---+
;|   |   |   |   |
;|r2 |O2@|   |   |
;|   |   |   |   |
;+---+---+---+---+
;|   |   |   |   |
;|r2 |r2 |h2 |h1 |
;|   |   |   |   |
;+---+---+---+---+
;|   |   |   |   |
;|   |r1 |r1 |h2 |
;|   |   |   |   |
;+---+---+---+---+
;|   |   |   |   |
;|   |O1 |r1 |h1 |
;|   |   |   |   |
;+---+---+---+---+

(def rivers '(((2 2) (3 2) (3 1) (2 1)) ((2 3) (1 3) (1 4) (2 4))))

(def holes '(((4 3) (4 1)) ((3 3) (4 2))))

(def max-x 4)
(def max-y 4)

(defn find? (p l) (any? (lambda (e) (= e p)) l))

(defn delemiter (i acc)
    (cond (<= i 0)
        (++ "+" acc \n)
        (delemiter (- i 1) (++ acc "---+"))))
(def delemiter-value (delemiter max-x ""))

(defn blank-line (i acc)
    (cond (<= i 0)
        (++ "|" acc \n)
        (blank-line (- i 1) (++ acc "   |"))))
(def blank-value (blank-line max-x ""))

(defn player-marker (p x y)
    (cond (= p (cons x y))
        "@"
        " "))

(defn river-number (p i l-riv)
   (cond (nil? l-riv)
        nil
        (cond (find? p (car l-riv))
            (cond (= p (car (reverse (car l-riv))))
                (++ "O" i)
                (++ "r" i))
            (river-number p (+ 1 i) (cdr l-riv)))))

(defn hole-number (p i l-hol)
    (cond (nil? l-hol)
        nil
        (cond (find? p (car l-hol))
            (++ "h" i)
            (hole-number p (+ 1 i) (cdr l-hol)))))

(defn null-safe (v d)
    (cond (nil? v)
        d
        v))

(defn curr-cell (p x y)
    (do
        (def xy (cons x y))
        (def r (river-number xy 1 rivers))
        (def o (null-safe r (hole-number xy 1 holes)))
        (def t (cond (nil? o) " " ""))
        (++ (null-safe o " ") (player-marker p x y) t)))

(defn field-line (p x y acc)
    (cond (> x max-x)
        (++ "|" acc \n)
        (field-line p (+ x 1) y (++ acc (curr-cell p x y) "|"))))

(defn field (p y acc)
    (cond (> y max-y)
        (++ acc delemiter-value)
        (field p (+ y 1) (++ delemiter-value blank-value (field-line p 1 y "") blank-value acc))))


(defn action-help (a)
    (cond (= a "h")
        (do
            (println "==Список команд:==")
            (println "================")
            (println "h - показать справку")
            (println "q - выход")
            (println "tm - скрыть/показать карту")
            (println "----------------")
            (println "w - шаг вверх")
            (println "a - шаг влево")
            (println "s - шаг вниз")
            (println "d - шаг вправо")
            true)
        false))

(defn action-move (a)
    (cond (= a "w")
        '(0 1)
        (cond (= a "s")
            '(0 -1)
            (cond (= a "a")
                '(-1 0)
                (cond (= a "d")
                    '(1 0)
                    false)))))

(defn action-quit (a)
    (cond (= a "q")
        'q
        false))

(defn action-toggle-map (a)
    (cond (= a "tm")
        'tm
        false))

(def stand-coord '(0 0))

(defn ask-action ()
    (do
        (print "input action: ")
        (def a (read))
        (def r (or (action-help a) (action-quit a) (action-toggle-map a) (action-move a)))
        (cond (= r false)
            (do
                (println "==Некорректная команда==")
                stand-coord)
            r)))

(defn check-rivers (p l-riv)
    (cond (nil? l-riv)
        false
        (cond (find? p (car l-riv))
            (car (reverse (car l-riv)))
            (check-rivers p (cdr l-riv)))))

(defn check-holes (p l-hol k)
    (cond (nil? l-hol)
        false
        (cond (find? p (car l-hol))
            (do
;                (print (++ "hole " k))
                (zip-with - (zip-with + (caar l-hol) (car (cdar l-hol))) p))
;                (cons (zip-with - (zip-with + (caar l-hol) (car (cdar l-hol))) p) k))
            (check-holes p (cdr l-hol) (+ k 1)))))

;(defn go (p a show?)
;    (do
;        (def new-p (zip-with + p a))
;        (def final-p
;            (cond (not (and (<= 1 (car new-p) max-x) (<= 1 (car (cdr new-p)) max-y)))
;                p
;                new-p))
;        (cond show? (print (field final-p 1 "")))
;        (go final-p (ask-dir) show?)))


;(defn in-range (p)
;    (cond

(defn go (p a map-show? continue?)
    (do
        (def new-p (zip-with + p a))
        (def final-p (cond (not (and (<= 1 (car new-p) max-x) (<= 1 (car (cdr new-p)) max-y)))
            p
            new-p))
        (cond map-show?
            (print (field final-p 1 "")))
        (def new-a (ask-action))
        (cond (not (= (typeof new-a) "ConsList"))
            (do
                (def new-a stand-coord)
                (cond (= new-a "q")
                    (def continue? (not continue?)))
                (cond (= new-a "tm")
                    (def map-show? (not map-show?)))))
        (cond continue?
            (go final-p new-a map-show? continue?)
            (println "Конец игры!"))))


(go '(0 0) '(1 1) true true)