(def nil '())
(def defmacro (macro (name args body) (def name (macro args body))))
(defmacro defn (name args body) (def name (lambda args body)))

(defn id (x) x)
(defmacro string? (x) (= (typeof x) "str"))
(defmacro int? (x) (= (typeof x) "int"))
(defmacro bool? (x) (= (typeof x) "bool"))
(defmacro list? (x) (= (typeof x) "ConsList"))
(defmacro lambda? (x) (= (typeof x) "Lambda"))
(defmacro macro? (x) (= (typeof x) "Macro"))
(defmacro float? (x) (= (typeof x) "float"))
(defmacro symbol? (x) (= (typeof x) "Symbol"))
(defmacro nil? (x) (= x '()))
(defmacro number? (x) (cond (int? x) true (float? x) true false))
(defmacro not (x) (cond x false true))
(def \n "
")


(def __and__ (lambda (args) (cond (= args '()) true (cond (= (cdr args) '()) (car args) (cons (cond (car args) (__and__ (cdr args)) false))))))
(def and (macro (args) (eval (__and__ 'args))))

(def __or__ (lambda (args) (cond (= args '()) false (cond (= (cdr args) '()) (car args) (cons (cond (cons (not (car args))) (__or__ (cdr args)) true))))))
(def or (macro (args) (eval (__or__ 'args))))


(def range (lambda (a b) (do (def t (lambda (b acc) (cond (> a b) acc (t (- b 1) (cons b acc))))) (t b '()))))

(def map-reverse-acc (lambda (f list acc) (cond (= list '()) acc (map-reverse-acc f (cdr list) (cons (f (car list)) acc)))) )
(def map (lambda (f list) (map-reverse-acc (lambda (x) x) (map-reverse-acc f list '()) '())))


(def def-m (macro (args) (cons do (g 'args))))
(def g (lambda (args)
    (cond (= args '())
        '()
        (= (cdr args) '())
            '()
            (cons (cons def (car args) (car (cdr args)) '()) (g (cdr (cdr args)))))))

(defn fold-left (f a l) (cond (nil? l) a (fold-left f (f a (car l)) (cdr l))))

;(defn __reverse__ (acc list) (cond (nil? list) acc (__reverse__ (cons (car list) acc) (cdr list))))
(defn reverse (list) (__reverse__ nil list));


(defn reverse (list) (fold-left (lambda (a e) (cons e a)) nil list))

;(defn length (list) (cond (nil? list) 0 (+ 1 (length (cdr list)))));

(defn sum (list) (fold-left (lambda (a e) (+ e a)) 0 list))

(defn length (list) (fold-left (lambda (a e) (+ 1 a)) 0 list))

(defn zip-with (f a b) cond (nil? a) nil (nil? b) nil (cons (f (car a) (car b)) (zip-with f (cdr a) (cdr b))))





(defn append-42 (a b)
    (cond (nil? a)
        b
        (append-42 (cdr a) (cons (car a) b))
    )
)

(defn append (a b) (append-42 (reverse a) b))

(defn concat (l) (cond (nil? l) () (append (car l) (concat (cdr l)))))

;(defn pg (tpl val)
;    (cond (list? tpl)
;        (cond (list? val)
;             (cond (= (length tpl) (length val))
;                   (concat (zip-with pg tpl val))
;                '((true false))
;             )
;            '((true false))
;        )
;        (cons (id tpl val) nil)
;    )
;)

(defn pg (tpl val)
  (cond (list? tpl)
        (cond (and (list? val) (= (length tpl) (length val)))
            (concat (zip-with pg tpl val))
            '((true false)))
        (cons (id tpl val) nil)))




(defn concat-ai (f tpl val)
  (cond (= nil tpl val) nil
        (or (nil? tpl) (nil? val)) false
        (do
          (def list1 (f (car tpl) (car val)))
          (def list2 (concat-ai f (cdr tpl) (cdr val)))
          (cond (and (list? list1) (list? list2))
            (append list1 list2)
            false))))


(defn pg-ai (tpl val)
  (cond (list? tpl)
        (cond (list? val)
            (concat-ai pg-ai tpl val)
            false)
        (symbol? tpl) (cond (= tpl '_) nil (cons (id def tpl (id quote val)) nil))
        (/= tpl val) false
        nil))

(defn check (list) (cond ((symbol? (car list))) true (not (list? (car list))) (= (car list) (car (cdr list))) (and (check (car list)) (check (cdr list)))))


(defn any? (p l) (cond (nil? l) false (cond (p (car l)) true (any? p (cdr l)))))

(defn fail? (l) (cond (symbol? (car l)) false (cond (/= (car l)) (car (cdr l)) true)))

(defn check-2 (list) (not (any? fail? list)))

(defn append-2 (a b) (cond (nil? a) b (cons (car a) (append-2 (cdr a) b))))

(defn def-gen (list)
  (cond (nil? list)
       '(true)
       (do
         (def p (car list) a (car p) b (car (cdr p)))
         (cond (and (symbol? a) (/= a '_)) (cons (id def a (id quote b)) (def-gen (cdr list)))
             (def-gen (cdr list))))))

;;(defn match-cor (tpl val) do
;;  (def a (pg tpl val))
;;     (cond (check-2 a) (cons do (def-gen a)) false));

(defn match-cor (tpl val)
  (do
  (def a (pg-ai tpl val))
  (cond (= a false) false
  (cons do (append a '(true))))))


(defmacro match (tpl val) (eval(match-cor 'tpl val)))

(defn filter (f l) (cond (nil? l) nil (cond (f (car l)) (cons (car l) (filter f (cdr l))) (filter f (cdr l)))))

(defn __take__ (acc n l) (cond (nil? l) nil (cond (= acc n) nil (cons (car l) (__take__ (+ acc 1) n (cdr l))))))
(defn take (n l) (__take__ 0 n l))

(defn __drop__ (acc n l) (cond (nil? l) nil (cond (<= acc n) (__drop__ (+ acc 1) n (cdr l)) (cons (car l) (__drop__ (+ acc 1) n (cdr l))))))
(defn drop (n l) (__drop__ 1 n l))

(defn __at-index__ (acc k l) (cond (nil? l) nil (cond (= acc k) (car l) (__at-index__ (+ acc 1) k (cdr l)))))
(defn at-index (k l) (__at-index__ 0 k l))
(defn __max__ (a b) (cond (< a b) b a))
(defn max (l) (cond (nil? l) nil (cond (nil? (cdr l)) (car l) (__max__ (car l) (max (cdr l))))))
(defn __min__ (a b) (cond (< a b) a b))
(defn min (l) (cond (nil? l) nil (cond (nil? (cdr l)) (car l) (__min__ (car l) (min (cdr l))))))

(defn abs (a) (cond (< a 0) (- 0 a) a))

(defn all? (f l) (cond (nil? l) true (cond (f (car l)) (all? f (cdr l)) false)))

(defn __span1__ (f l) (cond (nil? l) nil (cond (f (car l)) (cons (car l) (__span1__ f (cdr l))) (__span1__ f (cdr l)))))
(defn __span2__ (f l) (cond (nil? l) nil (cond (f (car l)) (__span2__ f (cdr l)) (cons (car l) (__span2__ f (cdr l))))))
(defn span (f l) (cons (__span1__ f l) (__span2__ f l) nil))


(defn __chunks-of__ (n l acc) (cond (nil? l) nil (cond (= n acc) (cons (take n l) (__chunks-of__ n (cdr l) 1)) (__chunks-of__ n (cdr l) (+ acc 1)))))
(defn chunks-of (n l) (__chunks-of__ n l n))

(defn nub (l) (cond (nil? l) nil (cons (car l) (nub (filter (lambda (x) (/= x (car l))) (cdr l))))))
